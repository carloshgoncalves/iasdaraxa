<?php

declare(strict_types=1);

namespace IasdCentral\Support;

use IasdCentral\Repositories\MemorialRepository;
use IasdCentral\Repositories\ObraRepository;
use IasdCentral\Repositories\SinapiRepository;

final class MemorialPlanner
{
    public function generate(int $obraId): array
    {
        $memorialRepository = new MemorialRepository();
        $memorial = $memorialRepository->findByObra($obraId);
        if (!$memorial || !(int) $memorial['ativo']) throw new \InvalidArgumentException('Ative e preencha o Memorial de Calculo antes de gerar o planejamento.');
        if ((float) $memorial['area_construida'] <= 0) throw new \InvalidArgumentException('Informe a area construida para gerar o planejamento.');

        $obraRepository = new ObraRepository();
        $obra = $obraRepository->find($obraId);
        $rules = $this->rules($memorial);
        $excluded = $memorialRepository->exclusions((int) $memorial['id']);
        $start = new \DateTimeImmutable($obra['data_inicio_prevista'] ?: 'today');
        $cursor = $start;
        $previousItemId = null;
        $created = 0;
        $updated = 0;

        foreach ($rules as $phaseIndex => $phase) {
            $activeTasks = array_values(array_filter($phase['tasks'], static fn (array $task): bool => $task['quantity'] > 0 && !in_array($task['code'], $excluded, true)));
            if ($activeTasks === []) continue;
            $phaseDays = array_sum(array_column($activeTasks, 'days'));
            $phaseStart = $cursor;
            $phaseEnd = $this->addBusinessDays($phaseStart, max(1, $phaseDays) - 1);
            [$phaseId, $phaseWasCreated] = $this->upsertItem($obraRepository, $memorialRepository, $obraId, (int) $memorial['id'], null, [
                'code' => 'phase.' . $phase['code'], 'name' => $phase['name'], 'quantity' => 1, 'unit' => 'etapa',
                'formula' => 'Agrupamento PMI das entregas da disciplina', 'days' => $phaseDays,
                'start' => $phaseStart, 'finish' => $phaseEnd, 'order' => ($phaseIndex + 1) * 10,
            ], []);
            $phaseWasCreated ? $created++ : $updated++;

            foreach ($activeTasks as $taskIndex => $task) {
                $finish = $this->addBusinessDays($cursor, max(1, $task['days']) - 1);
                $task['start'] = $cursor;
                $task['finish'] = $finish;
                $task['order'] = ($taskIndex + 1) * 10;
                [$itemId, $wasCreated] = $this->upsertItem($obraRepository, $memorialRepository, $obraId, (int) $memorial['id'], $phaseId, $task, $previousItemId ? [$previousItemId] : []);
                if ($wasCreated) {
                    $this->addComposition($obraRepository, $obraId, $itemId, $task);
                    $created++;
                } else {
                    $updated++;
                }
                $previousItemId = $itemId;
                $cursor = $this->nextBusinessDay($finish);
            }
        }
        $memorialRepository->markGenerated((int) $memorial['id']);
        return ['created' => $created, 'updated' => $updated, 'finish' => $cursor->modify('-1 day')->format('Y-m-d')];
    }

    private function upsertItem(ObraRepository $obras, MemorialRepository $memorials, int $obraId, int $memorialId, ?int $parentId, array $rule, array $predecessors): array
    {
        $existing = $memorials->findRule($memorialId, $rule['code']);
        $catalog = !empty($rule['sinapi']) ? (new SinapiRepository())->bestMatch('Servico', $rule['sinapi']) : null;
        $cost = (float) ($catalog['preco_mg'] ?? 0) * (float) $rule['quantity'];
        $data = [
            'parent_id'=>$parentId,'predecessor_ids'=>$predecessors,'nome'=>$rule['name'],
            'descricao'=>'Gerado pelo Memorial de Calculo. ' . $rule['formula'], 'responsavel'=>'', 'ordem'=>$rule['order'],
            'status'=>'Nao iniciada','progresso'=>0,'duracao_prevista_dias'=>$rule['days'],
            'data_inicio_prevista'=>$rule['start']->format('Y-m-d'),'data_fim_prevista'=>$rule['finish']->format('Y-m-d'),
            'custo_previsto'=>$cost,'marco'=>0,
        ];
        if ($existing) {
            $obras->updatePlanningItem($obraId, (int) $existing['item_id'], $data);
            $itemId = (int) $existing['item_id'];
            $created = false;
        } else {
            $itemId = $obras->createPlanningItem($obraId, $data);
            $created = true;
        }
        $memorials->link($memorialId, $itemId, $rule['code'], 'Gerado', (float) $rule['quantity'], $rule['unit'], $rule['formula']);
        return [$itemId, $created];
    }

    private function addComposition(ObraRepository $repository, int $obraId, int $itemId, array $rule): void
    {
        if (empty($rule['sinapi'])) return;
        $catalog = (new SinapiRepository())->bestMatch('Servico', $rule['sinapi']);
        if (!$catalog) return;
        $price = (float) ($catalog['preco_mg'] ?? 0);
        $repository->createService($obraId, [
            'item_id'=>$itemId,'fornecedor_id'=>null,'sinapi_id'=>(int)$catalog['id'],'nome'=>mb_substr((string)$catalog['descricao'],0,180),
            'escopo'=>'Composicao sugerida autonomamente pelo memorial','unidade'=>$catalog['unidade'],
            'quantidade'=>$rule['quantity'],'valor_previsto'=>$price * $rule['quantity'],'valor_contratado'=>0,
            'status'=>'Previsto','data_inicio'=>'','data_fim'=>'','observacoes'=>'Referencia SINAPI sugerida; validar com o responsavel tecnico.',
        ]);
    }

    private function rules(array $m): array
    {
        $area = (float)$m['area_construida']; $terrain=(float)$m['area_terreno']; $floors=max(1,(int)$m['pavimentos']);
        $footprint=$area/$floors; $perimeter=4*sqrt(max(1,$footprint));
        $wall=(float)($m['area_reboco']??0) ?: $area*3.0*((float)$m['pe_direito']/2.8);
        $roofMetal=(float)($m['area_telhado_metalico']??0); $roofCeramic=(float)($m['area_telhado_ceramico']??0);
        $roof=$roofMetal+$roofCeramic ?: ((float)$m['area_cobertura'] ?: $footprint*1.08); $external=(float)$m['area_externa'];
        $bath=max(0,(int)$m['banheiros']); $kitchens=max(0,(int)$m['cozinhas']); $months=max(1,(int)$m['meses_obra']);
        $isReform=$m['tipo_obra']==='Reforma';
        $complexity=['Residencial'=>1.0,'Comercial'=>1.05,'Industrial'=>1.25,'Institucional'=>1.10,'Reforma'=>1.15,'Infraestrutura'=>1.20,'Outro'=>1.0][$m['tipo_obra']]??1.0;
        $finishFactor=['Economico'=>0.85,'Padrao'=>1.0,'Alto'=>1.15,'Especial'=>1.30][$m['padrao_acabamento']]??1.0;
        $structureRates=['Concreto armado'=>[22.0,.16,'Armaduras da estrutura','armacao estrutura concreto armado'],'Estrutura metalica'=>[35.0,.05,'Estrutura metalica','montagem estrutura metalica'],'Alvenaria estrutural'=>[10.0,.10,'Armaduras e graute estrutural','alvenaria estrutural bloco concreto'],'Misto'=>[25.0,.14,'Estrutura mista - armaduras e perfis','estrutura concreto armado'],'Outro'=>[18.0,.12,'Estrutura conforme projeto executivo','estrutura concreto armado']][$m['sistema_estrutural']]??[22.0,.16,'Armaduras da estrutura','armacao estrutura concreto armado'];
        $subsoilFactor=(int)$m['possui_subsolo']===1?1.22:1.0;
        $electricAreaFactor=['Industrial'=>3,'Comercial'=>4,'Institucional'=>4,'Residencial'=>5,'Reforma'=>5,'Infraestrutura'=>6,'Outro'=>5][$m['tipo_obra']]??5;
        $task = static fn(string $code,string $name,float $qty,string $unit,string $formula,int $days,string $sinapi): array => ['code'=>$code,'name'=>$name,'quantity'=>$qty,'unit'=>$unit,'formula'=>$formula,'days'=>$days,'sinapi'=>$sinapi];
        return [
            ['code'=>'preliminares','name'=>'Servicos preliminares e gestao','tasks'=>[
                $task('limpeza','Limpeza do terreno',max($terrain,$footprint),'m2','max(area do terreno, projecao da edificacao)',max(2,(int)ceil(max($terrain,$footprint)/250)),'limpeza terreno manual'),
                $task('demolicao','Demolicoes e remocoes preliminares',$isReform?$area*.25:0,'m2','reforma: area construida x 0,25',max(2,(int)ceil($area*.25/25)),'demolicao alvenaria manual'),
                $task('canteiro','Canteiro, tapumes e instalacoes provisorias',max(1,(float)$m['testada']),'m','testada do terreno',4,'tapume chapa madeira'),
                $task('administracao','Administracao local da obra',$months,'mes','prazo global informado',max(5,$months*2),'administracao local obra'),
            ]],
            ['code'=>'terra','name'=>'Movimento de terra e fundacoes','tasks'=>[
                $task('escavacao','Escavacoes e preparo do terreno',$isReform?0:$terrain*.90*$subsoilFactor,'m3','area do terreno x 0,90 x fator subsolo',max(3,(int)ceil($terrain*.90*$subsoilFactor/45)),'escavacao mecanizada solo'),
                $task('fundacao','Fundacoes e blocos de coroamento',$isReform?0:((float)($m['comprimento_baldrame']??0) ?: $perimeter*$subsoilFactor),'m','baldrame informado ou perimetro estimado',max(5,(int)ceil($perimeter*$subsoilFactor/12)),'fundacao concreto armado'),
                $task('brocas','Brocas, estacas e elementos profundos',$isReform?0:(float)($m['quantidade_brocas']??0),'un','quantidade informada no memorial',max(2,(int)ceil((float)($m['quantidade_brocas']??0)/4)),'estaca broca concreto'),
                $task('aterro','Aterro, reaterro e compactacao',$isReform?0:$terrain*.35,'m3','area do terreno x 0,35',max(3,(int)ceil($terrain*.35/40)),'aterro compactado mecanizado'),
            ]],
            ['code'=>'estrutura','name'=>'Estrutura','tasks'=>[
                $task('aco',$structureRates[2],$isReform?0:((float)($m['peso_aco_estimado']??0) ?: $area*$structureRates[0]*$subsoilFactor),'kg','peso informado ou indice por area',max(7,(int)ceil($area/45*$complexity)),$structureRates[3]),
                $task('concreto','Concreto, graute e bases estruturais',$isReform?0:((float)($m['volume_concreto_estimado']??0) ?: $area*$structureRates[1]*$subsoilFactor),'m3','volume informado ou indice por area',max(6,(int)ceil($area/55*$complexity)),'concretagem estrutura concreto armado'),
                $task('lajes','Lajes, pisos estruturais e escoramentos',$isReform?0:$area,'m2','area construida quando houver estrutura nova',max(6,(int)ceil($area/55*$complexity)),'laje macica concreto armado'),
            ]],
            ['code'=>'vedacoes','name'=>'Vedacoes e revestimentos','tasks'=>[
                $task('alvenaria','Alvenarias e vedacoes',$wall/2,'m2','metade da area de revestimentos',max(8,(int)ceil(($wall/2)/35)),'alvenaria vedacao bloco ceramico'),
                $task('muro_divisa','Muros de divisa',(float)($m['comprimento_muro_divisa']??0),'m','comprimento informado no memorial',max(3,(int)ceil((float)($m['comprimento_muro_divisa']??0)/10)),'alvenaria vedacao bloco concreto'),
                $task('reboco','Chapisco, emboco e reboco',$wall,'m2','area informada ou area construida x 3,00',max(8,(int)ceil($wall/55)),'massa unica parede interna'),
                $task('revestimento_interno','Revestimentos internos',(float)($m['area_revestimento_interno']??0) ?: ($bath+$kitchens)*18*$finishFactor,'m2','area informada ou indice de ambientes molhados',max(4,(int)ceil((($bath+$kitchens)*18*$finishFactor)/30)),'revestimento ceramico parede'),
                $task('revestimento_externo','Revestimentos externos',(float)($m['area_revestimento_externo']??0),'m2','area informada no memorial',max(3,(int)ceil((float)($m['area_revestimento_externo']??0)/35)),'revestimento ceramico fachada'),
            ]],
            ['code'=>'instalacoes','name'=>'Instalacoes prediais','tasks'=>[
                $task('hidraulica','Instalacoes hidrossanitarias',(float)($m['pontos_hidraulicos']??0) ?: $bath*5+$kitchens*4,'ponto','pontos informados ou indice por ambientes',max(6,(int)ceil(($bath*5+$kitchens*4)/6)),'ponto consumo terminal agua fria'),
                $task('eletrica','Instalacoes eletricas',(float)($m['pontos_eletricos']??0) ?: (ceil($area/$electricAreaFactor)+(int)$m['unidades']*10)*$finishFactor,'ponto','pontos informados ou indice por tipologia',max(7,(int)ceil(((ceil($area/$electricAreaFactor)+(int)$m['unidades']*10)*$finishFactor)/12)),'ponto tomada residencial'),
                $task('elevador','Infraestrutura para elevador',(int)$m['possui_elevador'],'vb','opcao elevador do questionario',5,'instalacao elevador'),
            ]],
            ['code'=>'cobertura','name'=>'Cobertura e impermeabilizacao','tasks'=>[
                $task('telhado_metalico','Cobertura metalica',$roofMetal ?: ($roofCeramic>0?0:$roof),'m2','area informada ou cobertura estimada',max(5,(int)ceil($roof/35)),'telhamento telha metalica'),
                $task('telhado_ceramico','Cobertura ceramica',$roofCeramic,'m2','area informada no memorial',max(3,(int)ceil($roofCeramic/35)),'telhamento telha ceramica'),
                $task('calhas','Calhas',(float)($m['calhas_m']??0),'m','comprimento informado no memorial',max(2,(int)ceil((float)($m['calhas_m']??0)/25)),'calha chapa aco galvanizado'),
                $task('rufos','Rufos e arremates',(float)($m['rufos_m']??0),'m','comprimento informado no memorial',max(2,(int)ceil((float)($m['rufos_m']??0)/25)),'rufo chapa aco galvanizado'),
                $task('impermeabilizacao','Impermeabilizacoes',$footprint*0.22,'m2','projecao da edificacao x 0,22',max(3,(int)ceil($footprint*0.22/30)),'impermeabilizacao manta asfaltica'),
            ]],
            ['code'=>'acabamentos','name'=>'Acabamentos e esquadrias','tasks'=>[
                $task('pisos','Contrapisos e pisos',$area,'m2','area construida',max(8,(int)ceil($area/40)),'revestimento ceramico piso'),
                $task('forro_gesso','Forro de gesso',(float)($m['area_forro_gesso']??0) ?: (((float)($m['area_forro_madeira']??0)>0)?0:$area*.90),'m2','area informada ou area construida x 0,90',max(5,(int)ceil($area*.9/45)),'forro gesso acartonado'),
                $task('forro_madeira','Forro de madeira',(float)($m['area_forro_madeira']??0),'m2','area informada no memorial',max(3,(int)ceil((float)($m['area_forro_madeira']??0)/35)),'forro madeira'),
                $task('portas_madeira','Portas de madeira',(float)($m['quantidade_portas_madeira']??0),'un','quantidade informada no memorial',max(2,(int)ceil((float)($m['quantidade_portas_madeira']??0)/4)),'porta madeira instalacao'),
                $task('esquadrias_aluminio','Esquadrias de aluminio e vidros',(float)($m['quantidade_esquadrias_aluminio']??0) ?: max(8,(int)$m['unidades']*6),'un','quantidade informada ou unidades x 6',max(5,(int)ceil(max(8,(int)$m['unidades']*6)/4)),'porta aluminio vidro'),
                $task('guarda_corpo','Guarda-corpos e corrimaos',(float)($m['guarda_corpo_m']??0),'m','comprimento informado no memorial',max(2,(int)ceil((float)($m['guarda_corpo_m']??0)/12)),'guarda corpo aco galvanizado'),
                $task('pintura_interna','Pintura interna',(float)($m['area_pintura_interna']??0) ?: $wall,'m2','area informada ou paredes estimadas',max(6,(int)ceil($wall/80)),'pintura latex acrilica paredes'),
                $task('pintura_externa','Pintura externa',(float)($m['area_pintura_externa']??0) ?: $wall*.55,'m2','area informada ou indice de paredes externas',max(4,(int)ceil($wall*.55/80)),'pintura acrilica fachada'),
            ]],
            ['code'=>'marmoraria','name'=>'Marmoraria, loucas e metais','tasks'=>[
                $task('bancadas','Bancadas de pedra',(float)($m['quantidade_bancadas']??0),'un','quantidade informada no memorial',max(2,(int)ceil((float)($m['quantidade_bancadas']??0)/3)),'bancada granito'),
                $task('soleiras','Soleiras de pedra',(float)($m['soleiras_m']??0),'m','comprimento informado no memorial',max(2,(int)ceil((float)($m['soleiras_m']??0)/20)),'soleira granito'),
                $task('peitoris','Peitoris de pedra',(float)($m['peitoris_m']??0),'m','comprimento informado no memorial',max(2,(int)ceil((float)($m['peitoris_m']??0)/20)),'peitoril granito'),
                $task('bacias','Bacias sanitarias',(float)($m['quantidade_bacias']??0) ?: $bath,'un','quantidade informada ou numero de banheiros',max(2,(int)ceil(max(1,$bath)/4)),'vaso sanitario sifonado instalacao'),
                $task('torneiras','Torneiras e metais sanitarios',(float)($m['quantidade_torneiras']??0) ?: $bath+$kitchens,'un','quantidade informada ou ambientes molhados',max(2,(int)ceil(max(1,$bath+$kitchens)/5)),'torneira cromada instalacao'),
                $task('cubas','Cubas e tanques',(float)($m['quantidade_cubas']??0) ?: $kitchens,'un','quantidade informada ou numero de cozinhas',max(2,(int)ceil(max(1,$kitchens)/4)),'cuba aco inox instalacao'),
            ]],
            ['code'=>'externa','name'=>'Areas externas e entrega','tasks'=>[
                $task('pavimentacao','Pavimentacao e urbanizacao externa',$external,'m2','area externa informada',max(3,(int)ceil($external/40)),'execucao passeio concreto'),
                $task('deck','Deck',(float)($m['area_deck']??0),'m2','area informada no memorial',max(2,(int)ceil((float)($m['area_deck']??0)/20)),'piso madeira deck'),
                $task('paisagismo','Paisagismo',(float)($m['area_paisagismo']??0),'m2','area informada no memorial',max(2,(int)ceil((float)($m['area_paisagismo']??0)/40)),'plantio grama placas'),
                $task('piscina','Piscina e casa de maquinas',(float)($m['area_piscina']??0) ?: (int)$m['possui_piscina'],'m2','area informada ou opcao piscina',10,'revestimento ceramico piscina'),
                $task('limpeza_final','Limpeza final, testes e comissionamento',$area,'m2','area construida',max(3,(int)ceil($area/150)),'limpeza final obra'),
                $task('entrega','Entrega tecnica e encerramento',1,'vb','marco obrigatorio de encerramento',2,'entrega final obra'),
            ]],
        ];
    }

    private function addBusinessDays(\DateTimeImmutable $date, int $days): \DateTimeImmutable
    {
        while ($days > 0) { $date=$date->modify('+1 day'); if ((int)$date->format('N')<=5) $days--; }
        return $date;
    }
    private function nextBusinessDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        do { $date=$date->modify('+1 day'); } while ((int)$date->format('N')>5);
        return $date;
    }
}
