<?php

declare(strict_types=1);

namespace IasdCentral\Support;

use IasdCentral\Repositories\MemorialRepository;
use IasdCentral\Repositories\ObraRepository;
use IasdCentral\Repositories\SinapiRepository;

final class SinapiCompositionPlanner
{
    private const MANUAL_PATTERN = '/\b(outros?|honorarios?|honarios?|taxas?|custos? setor|depreciacao|combustivel|alimentacao|brindes?|checklist|termos? de entrega|projeto |plotagem|pasta para fiscalizacao|laudo cautelar|relatorio de sondagem|levantamento topografico|marcenaria|despesas? indiretas?|fretes?|compra de epis?|assessoria|marketing|banheira|beiral|paineis? ripados?|pecas? menores|pergolado|prateleiras?|porta principal.*acm|luminarias? especiais?|lustres?)\b/';

    public function compose(int $obraId): array
    {
        $obras = new ObraRepository();
        $sinapi = new SinapiRepository();
        $memorials = new MemorialRepository();
        $memorial = $memorials->findByObra($obraId) ?? [];
        $items = array_values(array_filter($obras->planningItems($obraId), static fn(array $item): bool => (int)$item['children_count'] === 0));
        $result = ['composed'=>0,'existing'=>0,'manual'=>0,'unmatched'=>0,'resources'=>0];

        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            if ($obras->itemResourceCount($obraId, $itemId) > 0) {
                $memorials->saveCompositionAnalysis($itemId, 'Existente', 100, 'Composicao existente preservada.');
                $result['existing']++;
                continue;
            }
            $text = $this->normalize(($item['parent_name'] ?? '') . ' ' . $item['nome']);
            if (preg_match(self::MANUAL_PATTERN, $text)) {
                $memorials->saveCompositionAnalysis($itemId, 'Revisao manual', 20, 'Item autoral, administrativo ou sem composicao padronizada segura no SINAPI.');
                $result['manual']++;
                continue;
            }

            $profile = $this->profile($text, (string)$item['nome']);
            $added = 0;
            foreach ($profile['resources'] as $resource) {
                $catalog = $sinapi->bestMatch($resource['type'], $resource['query']);
                if (!$catalog || $obras->compositionResourceExists($obraId, $itemId, (int)$catalog['id'], $resource['type'])) continue;
                $quantity = $this->quantity($text, (string)$catalog['unidade'], $memorial, (float)$resource['factor']);
                $this->createResource($obras, $obraId, $itemId, $catalog, $resource['type'], $quantity, $profile['reason']);
                $added++;
            }
            if ($added > 0) {
                $memorials->saveCompositionAnalysis($itemId, 'Composta', $profile['confidence'], $profile['reason'].' Quantitativo preliminar pelo Memorial; requer conferencia tecnica.');
                $result['composed']++;
                $result['resources'] += $added;
            } else {
                $memorials->saveCompositionAnalysis($itemId, 'Sem correspondencia', 0, 'Nenhuma correspondencia SINAPI suficientemente segura foi localizada.');
                $result['unmatched']++;
            }
        }
        return $result;
    }

    private function profile(string $text, string $original): array
    {
        $rules = [
            ['/betoneira/', [['Equipamento','betoneira capacidade',1]], 'Equipamento diretamente identificado.'],
            ['/compactador.*solo|tipo sapo/', [['Equipamento','compactador solo percussao',1]], 'Equipamento diretamente identificado.'],
            ['/vibrador.*concret/', [['Equipamento','vibrador imersao concreto',1]], 'Equipamento diretamente identificado.'],
            ['/martelete|rompedor/', [['Servico','martelete perfurador rompedor eletrico chi diurno',1]], 'Equipamento por hora produtiva diretamente identificado.'],
            ['/bomba lanca/', [['Equipamento','bomba concreto lança',1]], 'Equipamento diretamente identificado.'],
            ['/munck/', [['Equipamento','caminhao guindauto munck',1]], 'Equipamento diretamente identificado.'],
            ['/escavacao.*mecan|bobcat/', [['Servico','escavacao mecanizada solo',1],['Equipamento','escavadeira hidraulica',1]], 'Movimento de terra mecanizado.'],
            ['/escavacao|vala/', [['Servico','escavacao manual vala',1]], 'Movimento de terra manual.'],
            ['/aterro|compactacao/', [['Servico','aterro mecanizado compactado',1],['Material','argila aterro',1]], 'Aterro e compactacao.'],
            ['/armacao|aco ca|resumo de aco/', [['Servico','armacao estrutura concreto armado',1],['Material','aco ca-50 barra',1]], 'Armadura estrutural.'],
            ['/forma|formas para/', [['Servico','fabricacao montagem forma madeira',1],['Material','chapa madeira compensada resinada',.12]], 'Sistema de formas.'],
            ['/concretagem|concreto armado|concreto usinado|resumo de concreto/', [['Servico','concretagem estrutura concreto armado',1],['Material','concreto usinado bombeavel',1],['Equipamento','vibrador imersao concreto',.02]], 'Concretagem estrutural.'],
            ['/impermeabil/', [['Servico','impermeabilizacao manta asfaltica',1],['Material','manta asfaltica impermeabilizacao',1.1]], 'Impermeabilizacao.'],
            ['/perfuracao.*broca|estacas? brocas?/', [['Servico','estaca broca concreto',1],['Material','concreto usinado',.12]], 'Fundacao em estaca broca.'],
            ['/tijolo|alvenaria|bloco|muro de divisa/', [['Servico','alvenaria vedacao bloco ceramico',1],['Material','bloco ceramico vedacao',13.5]], 'Alvenaria de vedacao.'],
            ['/chapisco/', [['Servico','chapisco aplicado alvenaria',1],['Material','cimento portland composto',.004]], 'Chapisco de base.'],
            ['/reboco|massa fina|massa unica/', [['Servico','massa unica parede interna',1],['Material','argamassa industrializada revestimento',.02]], 'Revestimento argamassado.'],
            ['/telhado|telha/', [['Servico','telhamento telha metalica',1],['Material','telha metalica trapezoidal',1.1]], 'Cobertura e telhamento.'],
            ['/calha/', [['Servico','calha chapa aco galvanizado',1],['Material','chapa aco galvanizada',1.1]], 'Calha metalica.'],
            ['/rufo/', [['Servico','rufo chapa aco galvanizado',1],['Material','chapa aco galvanizada',1.1]], 'Rufo metalico.'],
            ['/forro.*gesso/', [['Servico','forro gesso acartonado',1],['Material','chapa gesso acartonado',1.1]], 'Forro de gesso.'],
            ['/forro.*madeira/', [['Servico','forro madeira',1],['Material','forro madeira',1.1]], 'Forro de madeira.'],
            ['/fiacao|cabos eletricos/', [['Servico','instalacao cabo cobre flexivel',1],['Material','cabo cobre flexivel',1]], 'Circuitos eletricos.'],
            ['/quadro.*disjunt|disjuntores/', [['Servico','quadro distribuicao energia disjuntores',1],['Material','quadro distribuicao disjuntores',1]], 'Quadro eletrico.'],
            ['/tomadas|interruptores|caixinhas|eletroduto|mangueira corrugada|pontos? de infra.*eletrodomesticos/', [['Servico','ponto tomada residencial',1],['Material','eletroduto pvc flexivel corrugado',3]], 'Pontos eletricos embutidos.'],
            ['/hidraulic|agua pluvial|esgoto|caixa d agua|tubulacao/', [['Servico','ponto consumo terminal agua fria',1],['Material','tubo pvc soldavel agua fria',3]], 'Instalacao hidrossanitaria.'],
            ['/piso|revestimento|ceramic|porcelanato|contrapiso/', [['Servico','revestimento ceramico piso',1],['Material','placa ceramica esmaltada',1.1]], 'Pisos e revestimentos.'],
            ['/esquadria.*aluminio|porta.*aluminio|janela/', [['Servico','porta aluminio vidro',1],['Material','porta aluminio',1]], 'Esquadria de aluminio.'],
            ['/esquadria.*madeira|porta.*madeira/', [['Servico','porta madeira instalacao',1],['Material','folha porta madeira',1]], 'Esquadria de madeira.'],
            ['/guarda.?corpo/', [['Servico','guarda corpo aco galvanizado',1],['Material','tubo aco galvanizado',1.2]], 'Guarda-corpo.'],
            ['/bancada|marmoraria|soleira|peitoril|pedra moledo|granito/', [['Servico','bancada granito',1],['Material','granito para bancada',1]], 'Marmoraria e pedras.'],
            ['/bacias? sanitarias?/', [['Servico','bacia sanitaria louca branca fornecimento instalacao',1],['Material','bacia sanitaria vaso caixa acoplada',1]], 'Louca sanitaria.'],
            ['/torneira|registro|metais internos|metais externos|chuveiro/', [['Servico','torneira cromada instalacao',1],['Material','torneira cromada',1]], 'Metais sanitarios.'],
            ['/cuba|tanque/', [['Servico','cuba aco inox instalacao',1],['Material','cuba aco inox',1]], 'Cubas e tanques.'],
            ['/pintura/', [['Servico','pintura latex acrilica paredes',1],['Material','tinta latex acrilica premium',.16]], 'Pintura de superficies.'],
            ['/deck.*madeira/', [['Servico','piso madeira deck',1],['Material','madeira deck',1.1]], 'Deck de madeira.'],
            ['/paisagismo|plantas ornamentais/', [['Servico','plantio grama placas',1],['Material','muda ornamental',.2]], 'Paisagismo.'],
            ['/piscina/', [['Servico','revestimento ceramico piscina',1]], 'Servico relacionado a piscina.'],
            ['/limpeza/', [['Servico','limpeza final obra',1]], 'Limpeza de obra.'],
            ['/tapume/', [['Servico','tapume chapa madeira',1],['Material','chapa madeira compensada',.5]], 'Fechamento provisorio.'],
            ['/locacao da obra|gabarito/', [['Servico','locacao convencional obra gabarito',1]], 'Locacao e gabarito.'],
            ['/placa de identificacao/', [['Servico','placa obra chapa galvanizada',1]], 'Identificacao de obra.'],
            ['/passeio|via publica|pavimentacao/', [['Servico','execucao passeio concreto',1]], 'Pavimentacao externa.'],
            ['/andaime/', [['Equipamento','locacao andaime metalico',1]], 'Andaime para acesso e trabalho em altura.'],
            ['/container/', [['Equipamento','locacao container escritorio',1]], 'Container de apoio ao canteiro.'],
            ['/cacamba/', [['Equipamento','cacamba estacionaria entulho',1]], 'Remocao e acondicionamento de residuos.'],
            ['/caixa d agua/', [['Material','reservatorio polietileno 1000 litros',1]], 'Reservacao de agua.'],
            ['/escoramento/', [['Material','viga escoramento madeira',1],['Material','pontalete eucalipto escoramento',1]], 'Escoramento estrutural.'],
            ['/port(ao|oes)/', [['Material','portao aco galvanizado',1]], 'Portao metalico.'],
            ['/cortes e passagens|preparacao interna.*fechamentos|requadros/', [['Servico','massa unica parede interna',1]], 'Preparacao e arremate de vedacoes.'],
            ['/ligacoes? provisorias?.*agua|cavalete copasa/', [['Servico','cavalete agua pvc instalacao',1]], 'Ligacao provisoria de agua.'],
            ['/ligacoes? definitivas?.*energia|padrao tri/', [['Servico','entrada energia eletrica trifasica',1]], 'Ligacao definitiva de energia.'],
            ['/tenda.*protecao provisoria/', [['Equipamento','locacao tenda',1]], 'Protecao provisoria do canteiro.'],
            ['/patamares? de entrada/', [['Servico','execucao passeio concreto',1]], 'Piso de acesso externo.'],
            ['/levantamento e isolamento/', [['Servico','tapume chapa madeira',1]], 'Isolamento da frente de servico.'],
        ];
        foreach ($rules as [$pattern,$resources,$reason]) {
            if (!preg_match($pattern,$text)) continue;
            return ['resources'=>array_map(static fn(array $r): array => ['type'=>$r[0],'query'=>$r[1],'factor'=>$r[2]],$resources),'confidence'=>90,'reason'=>$reason];
        }
        $query = trim(preg_replace('/\b(instalacao|servico|material|insumos|resumo|outros)\b/',' ',$this->normalize($original)));
        return ['resources'=>[['type'=>'Servico','query'=>$query,'factor'=>1]],'confidence'=>55,'reason'=>'Correspondencia textual com a descricao do item.'];
    }

    private function quantity(string $text, string $unit, array $m, float $factor): float
    {
        $area=(float)($m['area_construida']??0) ?: 1; $terrain=(float)($m['area_terreno']??0) ?: $area;
        $wall=(float)($m['area_reboco']??0) ?: $area*3; $roof=(float)($m['area_cobertura']??0) ?: $area/max(1,(int)($m['pavimentos']??1))*1.08;
        $quantity = 1.0;
        if (preg_match('/aco ca|armacao|resumo de aco/',$text)) $quantity=(float)($m['peso_aco_estimado']??0) ?: $area*22;
        elseif (preg_match('/concreto/',$text)) $quantity=(float)($m['volume_concreto_estimado']??0) ?: $area*.16;
        elseif (preg_match('/escavacao|aterro/',$text)) $quantity=$terrain*.90;
        elseif (preg_match('/reboco|chapisco|pintura/',$text)) $quantity=$wall;
        elseif (preg_match('/telhado|telha/',$text)) $quantity=$roof;
        elseif (preg_match('/forro/',$text)) $quantity=((float)($m['area_forro_gesso']??0)+(float)($m['area_forro_madeira']??0)) ?: $area*.9;
        elseif (preg_match('/piso|revestimento|impermeabil/',$text)) $quantity=$area;
        elseif (preg_match('/alvenaria|tijolo|bloco/',$text)) $quantity=$wall/2;
        elseif (preg_match('/bancada/',$text)) $quantity=(float)($m['quantidade_bancadas']??0) ?: max(1,(int)($m['cozinhas']??1));
        elseif (preg_match('/soleira/',$text)) $quantity=(float)($m['soleiras_m']??0) ?: 1;
        elseif (preg_match('/peitoril/',$text)) $quantity=(float)($m['peitoris_m']??0) ?: 1;
        elseif (preg_match('/bacia sanitaria/',$text)) $quantity=(float)($m['quantidade_bacias']??0) ?: max(1,(int)($m['banheiros']??1));
        elseif (preg_match('/torneira|registro|metais/',$text)) $quantity=(float)($m['quantidade_torneiras']??0) ?: max(1,(int)($m['banheiros']??1)+(int)($m['cozinhas']??1));
        elseif (preg_match('/cuba|tanque/',$text)) $quantity=(float)($m['quantidade_cubas']??0) ?: max(1,(int)($m['cozinhas']??1));
        elseif (preg_match('/porta.*madeira/',$text)) $quantity=(float)($m['quantidade_portas_madeira']??0) ?: 1;
        elseif (preg_match('/esquadria.*aluminio|janela/',$text)) $quantity=(float)($m['quantidade_esquadrias_aluminio']??0) ?: 1;
        elseif (preg_match('/calha/',$text)) $quantity=(float)($m['calhas_m']??0) ?: $roof*.25;
        elseif (preg_match('/rufo/',$text)) $quantity=(float)($m['rufos_m']??0) ?: $roof*.20;
        elseif (preg_match('/deck/',$text)) $quantity=(float)($m['area_deck']??0) ?: 1;
        elseif (preg_match('/paisagismo/',$text)) $quantity=(float)($m['area_paisagismo']??0) ?: 1;
        elseif (preg_match('/piscina/',$text)) $quantity=(float)($m['area_piscina']??0) ?: 1;
        elseif (preg_match('/eletric|tomada|fiacao|disjunt/',$text)) $quantity=(float)($m['pontos_eletricos']??0) ?: ceil($area/4);
        elseif (preg_match('/hidraulic|esgoto|agua/',$text)) $quantity=(float)($m['pontos_hidraulicos']??0) ?: ((int)($m['banheiros']??1)*5+(int)($m['cozinhas']??1)*4);
        elseif (preg_match('/limpeza|tapume|passeio|paviment/',$text)) $quantity=$terrain;
        if (in_array(mb_strtolower($unit),['un','unid','und','vb','mes'],true) && $quantity<1) $quantity=1;
        return max(.001,round($quantity*$factor,4));
    }

    private function createResource(ObraRepository $repository, int $obraId, int $itemId, array $catalog, string $type, float $quantity, string $reason): void
    {
        $common=['item_id'=>$itemId,'fornecedor_id'=>null,'sinapi_id'=>(int)$catalog['id'],'nome'=>mb_substr((string)$catalog['descricao'],0,180),'unidade'=>$catalog['unidade'],'status'=>'Previsto','observacoes'=>'Sugestao automatica SINAPI: '.$reason];
        $price=(float)($catalog['preco_mg']??0);
        if($type==='Material') $repository->createMaterial($obraId,$common+['especificacao'=>'','quantidade_prevista'=>$quantity,'quantidade_comprada'=>0,'valor_unitario_previsto'=>$price,'valor_unitario_real'=>0,'data_necessidade'=>'']);
        elseif($type==='Equipamento') $repository->createEquipment($obraId,$common+['quantidade_prevista'=>$quantity,'quantidade_real'=>0,'valor_unitario_previsto'=>$price,'valor_unitario_real'=>0,'data_inicio'=>'','data_fim'=>'']);
        else $repository->createService($obraId,$common+['escopo'=>'Composicao SINAPI sugerida','quantidade'=>$quantity,'valor_previsto'=>$quantity*$price,'valor_contratado'=>0,'data_inicio'=>'','data_fim'=>'']);
    }

    private function normalize(string $value): string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value) ?: $value;
        return trim(preg_replace('/\s+/',' ',mb_strtolower($ascii)));
    }
}
