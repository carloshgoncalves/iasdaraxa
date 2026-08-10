<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\Response;
use IasdCentral\Core\View;
use IasdCentral\Repositories\MenuRepository;
use IasdCentral\Repositories\MemorialRepository;
use IasdCentral\Repositories\ObraRepository;
use IasdCentral\Repositories\SinapiRepository;
use IasdCentral\Support\SimplePdf;
use IasdCentral\Support\Spreadsheet;
use IasdCentral\Support\MemorialPlanner;
use IasdCentral\Support\SinapiCompositionPlanner;

final class ObrasController
{
    public function index(Request $request): void
    {
        $user = Auth::requireLogin();
        $repository = new ObraRepository();

        View::render('modules/obras/index', [
            'title' => 'Obras',
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'summary' => $repository->dashboardSummary(),
            'obras' => $repository->all(),
            'planningModels' => $repository->planningModels(),
        ]);
    }

    public function store(Request $request): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $data = $this->obraData($request);
        if ($data['nome'] === '') {
            $_SESSION['flash_error'] = 'Informe o nome da obra.';
            redirect('/obras');
        }

        $id = (new ObraRepository())->createObra($data, (int) $user['id']);
        $modelId = (int) $request->input('modelo_id', 0);
        if ($modelId > 0) {
            (new ObraRepository())->applyPlanningModel($id, $modelId);
        }
        $_SESSION['flash_success'] = 'Obra cadastrada.';
        redirect('/obras/' . $id);
    }

    public function show(Request $request, string $id): void
    {
        $user = Auth::requireLogin();
        $repository = new ObraRepository();
        $obra = $repository->find((int) $id);
        if (!$obra) {
            $_SESSION['flash_error'] = 'Obra nao encontrada.';
            redirect('/obras');
        }

        $obraId = (int) $obra['id'];
        $editingItemId = (int) $request->input('editar_item', 0);
        $compositionItemId = (int) $request->input('composicao_item', 0);
        $editingResourceType = (string) $request->input('editar_recurso_tipo', '');
        $editingResourceId = (int) $request->input('editar_recurso_id', 0);
        $planningItems = $repository->planningItems($obraId);
        $editingItem = $editingItemId > 0 ? $repository->findPlanningItem($obraId, $editingItemId) : null;
        $editingResource = $editingResourceId > 0 ? $repository->findCompositionResource($obraId, $editingResourceType, $editingResourceId) : null;
        if ($editingResource) {
            $compositionItemId = (int) $editingResource['item_id'];
        }
        $compositionItem = $compositionItemId > 0 ? $repository->findPlanningItem($obraId, $compositionItemId) : null;
        $sinapiReference = (new SinapiRepository())->referenceSummary();
        $memorialRepository = new MemorialRepository();
        $memorial = $memorialRepository->findByObra($obraId);
        $compositionAnalysis = $memorialRepository->compositionAnalysis($obraId);
        $calendarMonth = preg_match('/^\d{4}-\d{2}$/', (string)$request->input('obra_mes')) ? (string)$request->input('obra_mes') : date('Y-m');
        View::render('modules/obras/show', [
            'title' => 'Obra: ' . $obra['nome'],
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'obra' => $obra,
            'overview' => $repository->overview($obraId),
            'planningItems' => $planningItems,
            'itemOptions' => $planningItems,
            'parentOptions' => $repository->itemOptions($obraId, $editingItem ? (int) $editingItem['id'] : null),
            'editingItem' => $editingItem,
            'editingItemMemorialLink' => $editingItem ? $memorialRepository->linkedItem($obraId, (int)$editingItem['id']) : null,
            'selectedParentId' => $editingItem ? (int) ($editingItem['parent_id'] ?? 0) : (int) $request->input('parent_item', 0),
            'openPlanningItemDialog' => $editingItem !== null || (int) $request->input('novo_item', 0) === 1 || (int) $request->input('parent_item', 0) > 0,
            'compositionItem' => $compositionItem,
            'openCompositionDialog' => $compositionItem !== null,
            'editingResource' => $editingResource,
            'editingResourceType' => $editingResource ? $editingResourceType : null,
            'fornecedores' => $repository->fornecedores(),
            'sinapiReference' => $sinapiReference,
            'memorial' => $memorial,
            'memorialLinks' => $memorialRepository->linksByObra($obraId),
            'compositionAnalysis' => $compositionAnalysis,
            'planningCalendarMonth' => $calendarMonth,
            'planningCalendar' => $this->planningCalendar($calendarMonth, $planningItems),
            'gantt' => $this->ganttData($planningItems),
            'planningSubView' => in_array((string)$request->input('visao'), ['calendar','memorial','planning','gantt'], true) ? (string)$request->input('visao') : 'planning',
            'finance' => $repository->financeSummary($obraId),
            'movements' => $repository->movements($obraId),
            'diary' => $repository->diary($obraId),
        ]);
    }

    public function sinapiCatalog(Request $request): void
    {
        Auth::requireLogin();
        $type = (string) $request->input('tipo');
        $query = trim((string) $request->input('q'));
        $page = max(1, (int) $request->input('page', 1));
        Response::json((new SinapiRepository())->search($type, $query, $page));
    }

    public function storeItem(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $data = $this->itemData($request);
        if ($data['nome'] === '') {
            $_SESSION['flash_error'] = 'Informe o nome do item.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        try {
            $itemId = (new ObraRepository())->createPlanningItem((int) $id, $data);
            $this->syncManualMemorialLink((int)$id, $itemId, $request);
            $_SESSION['flash_success'] = 'Item adicionado ao planejamento.';
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['flash_error'] = $exception->getMessage();
        }
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function updateItem(Request $request, string $id, string $itemId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $data = $this->itemData($request);
        if ($data['nome'] === '') {
            $_SESSION['flash_error'] = 'Informe o nome do item.';
            redirect('/obras/' . (int) $id . '?editar_item=' . (int) $itemId . '#planejamento');
        }
        try {
            $updated = (new ObraRepository())->updatePlanningItem((int) $id, (int) $itemId, $data);
            if ($updated) $this->syncManualMemorialLink((int)$id, (int)$itemId, $request);
            $_SESSION[$updated ? 'flash_success' : 'flash_error'] = $updated ? 'Item atualizado.' : 'Item nao encontrado.';
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['flash_error'] = $exception->getMessage();
            redirect('/obras/' . (int) $id . '?editar_item=' . (int) $itemId . '#planejamento');
        }
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function destroyItem(Request $request, string $id, string $itemId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $memorials = new MemorialRepository();
        $link = $memorials->linkedItem((int)$id, (int)$itemId);
        if ($link && (string)$request->input('memorial_action', 'suppress') === 'suppress') {
            $memorials->suppressRule((int)$link['memorial_id'], (string)$link['regra_codigo']);
        }
        $deleted = (new ObraRepository())->deletePlanningItem((int) $id, (int) $itemId);
        $_SESSION[$deleted ? 'flash_success' : 'flash_error'] = $deleted
            ? 'Item e seus subitens removidos.'
            : 'Item nao encontrado.';
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function moveItem(Request $request, string $id, string $itemId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $parentId = (int) $request->input('parent_id', 0);
        try {
            $moved = (new ObraRepository())->movePlanningItem((int) $id, (int) $itemId, $parentId > 0 ? $parentId : null);
            Response::json(['ok' => $moved]);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function saveMemorial(Request $request, string $id): void
    {
        Csrf::verify(); Auth::requireLogin();
        $obraId=(int)$id;
        $data=$this->memorialData($request);
        (new MemorialRepository())->save($obraId,$data);
        if ((int)$request->input('generate',0) === 1 && $data['ativo']) {
            try {
                $result=(new MemorialPlanner())->generate($obraId);
                $_SESSION['flash_success']='Memorial salvo. Planejamento recalculado: '.$result['created'].' itens criados e '.$result['updated'].' atualizados.';
            } catch (\Throwable $exception) { $_SESSION['flash_error']=$exception->getMessage(); }
        } else { $_SESSION['flash_success']='Memorial de Calculo salvo.'; }
        redirect('/obras/'.$obraId.'#planejamento');
    }

    public function generateMemorial(Request $request, string $id): void
    {
        Csrf::verify(); Auth::requireLogin();
        try {
            $result=(new MemorialPlanner())->generate((int)$id);
            $_SESSION['flash_success']='Planejamento recalculado: '.$result['created'].' itens criados, '.$result['updated'].' atualizados. Termino previsto em '.date_br($result['finish']).'.';
        } catch (\Throwable $exception) { $_SESSION['flash_error']=$exception->getMessage(); }
        redirect('/obras/'.(int)$id.'#planejamento');
    }

    public function composePlanning(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        try {
            $result = (new SinapiCompositionPlanner())->compose((int) $id);
            $_SESSION['flash_success'] = sprintf(
                'Composicoes SINAPI processadas: %d tarefas compostas, %d recursos adicionados, %d existentes preservadas e %d para revisao.',
                $result['composed'],
                $result['resources'],
                $result['existing'],
                $result['manual'] + $result['unmatched']
            );
        } catch (\Throwable $exception) {
            $_SESSION['flash_error'] = 'Nao foi possivel compor o planejamento: ' . $exception->getMessage();
        }
        redirect('/obras/' . (int) $id . '?visao=planning#planejamento');
    }

    public function exportCalendar(Request $request, string $id): void
    {
        Auth::requireLogin();
        $obra=(new ObraRepository())->find((int)$id);
        if (!$obra) { http_response_code(404); return; }
        $items=(new ObraRepository())->planningItems((int)$id);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="obra-'.(int)$id.'-planejamento.ics"');
        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//IASD Central//Planejamento//PT-BR\r\nCALSCALE:GREGORIAN\r\n";
        foreach ($items as $item) {
            if (empty($item['data_inicio_prevista'])) continue;
            $start=str_replace('-','',(string)$item['data_inicio_prevista']);
            $end=(new \DateTimeImmutable($item['data_fim_prevista'] ?: $item['data_inicio_prevista']))->modify('+1 day')->format('Ymd');
            $summary=$this->icsText($item['wbs'].' '.$item['nome']);
            echo "BEGIN:VEVENT\r\nUID:obra-{$id}-item-{$item['id']}@iasdaraxa\r\nDTSTART;VALUE=DATE:{$start}\r\nDTEND;VALUE=DATE:{$end}\r\nSUMMARY:{$summary}\r\nDESCRIPTION:".$this->icsText('Obra: '.$obra['nome'].' | Status: '.$item['status'])."\r\nEND:VEVENT\r\n";
        }
        echo "END:VCALENDAR\r\n";
    }

    public function storeMaterial(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $repository = new ObraRepository();
        $data = $this->materialData($request);
        $catalogItem = (new SinapiRepository())->findForType((int) $request->input('sinapi_id', 0), 'Material');
        if (!$catalogItem) {
            $_SESSION['flash_error'] = 'Selecione um material da lista SINAPI.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        $data = $this->applyCatalogItem($data, $catalogItem, 'valor_unitario_previsto');
        $data['fornecedor_id'] = $repository->createFornecedorIfNeeded((string) $request->input('fornecedor_nome'));
        $repository->createMaterial((int) $id, $data);
        $_SESSION['flash_success'] = 'Material registrado.';
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function storeService(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $repository = new ObraRepository();
        $data = $this->serviceData($request);
        $catalogItem = (new SinapiRepository())->findForType((int) $request->input('sinapi_id', 0), 'Servico');
        if (!$catalogItem) {
            $_SESSION['flash_error'] = 'Selecione um servico da lista SINAPI.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        $data = $this->applyCatalogItem($data, $catalogItem);
        $data['valor_previsto'] = (float) $data['quantidade'] * (float) ($catalogItem['preco_mg'] ?? 0);
        $data['fornecedor_id'] = $repository->createFornecedorIfNeeded((string) $request->input('fornecedor_nome'));
        $repository->createService((int) $id, $data);
        $_SESSION['flash_success'] = 'Servico registrado.';
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function storeEquipment(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $repository = new ObraRepository();
        $data = $this->equipmentData($request);
        $catalogItem = (new SinapiRepository())->findForType((int) $request->input('sinapi_id', 0), 'Equipamento');
        if (!$catalogItem) {
            $_SESSION['flash_error'] = 'Selecione um equipamento da lista SINAPI.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        $data = $this->applyCatalogItem($data, $catalogItem, 'valor_unitario_previsto');
        $data['fornecedor_id'] = $repository->createFornecedorIfNeeded((string) $request->input('fornecedor_nome'));
        $repository->createEquipment((int) $id, $data);
        $_SESSION['flash_success'] = 'Equipamento registrado.';
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function storeComposition(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $obraId = (int) $id;
        $itemId = (int) $request->input('item_id', 0);
        $type = $this->option($request, 'resource_type', ['Material', 'Servico', 'Equipamento'], 'Material');
        $repository = new ObraRepository();
        if (!$repository->findPlanningItem($obraId, $itemId)) {
            $_SESSION['flash_error'] = 'Selecione um item valido do planejamento.';
            redirect('/obras/' . $obraId . '#planejamento');
        }

        $catalogItem = (new SinapiRepository())->findForType((int) $request->input('sinapi_id', 0), $type);
        if (!$catalogItem) {
            $_SESSION['flash_error'] = 'Selecione uma opcao da lista SINAPI.';
            redirect('/obras/' . $obraId . '?composicao_item=' . $itemId . '#planejamento');
        }

        $quantity = max(0, (float) $this->money($request->input('quantidade', '1')));
        $supplierId = $repository->createFornecedorIfNeeded((string) $request->input('fornecedor_nome'));
        $price = (string) ($catalogItem['preco_mg'] ?? 0);
        $common = [
            'item_id' => $itemId,
            'fornecedor_id' => $supplierId,
            'sinapi_id' => (int) $catalogItem['id'],
            'nome' => (string) $catalogItem['descricao'],
            'unidade' => (string) $catalogItem['unidade'],
            'status' => $this->resourceStatus($request, $type),
            'observacoes' => trim((string) $request->input('observacoes')),
        ];

        if ($type === 'Material') {
            $repository->createMaterial($obraId, $common + [
                'especificacao' => '', 'quantidade_prevista' => $quantity, 'quantidade_comprada' => 0,
                'valor_unitario_previsto' => $price, 'valor_unitario_real' => 0, 'data_necessidade' => '',
            ]);
        } elseif ($type === 'Servico') {
            $repository->createService($obraId, $common + [
                'escopo' => '', 'quantidade' => $quantity,
                'valor_previsto' => $quantity * (float) $price, 'valor_contratado' => 0,
                'data_inicio' => '', 'data_fim' => '',
            ]);
        } else {
            $repository->createEquipment($obraId, $common + [
                'quantidade_prevista' => $quantity, 'quantidade_real' => 0,
                'valor_unitario_previsto' => $price, 'valor_unitario_real' => 0,
                'data_inicio' => '', 'data_fim' => '',
            ]);
        }

        $_SESSION['flash_success'] = $type . ' adicionado a composicao.';
        redirect('/obras/' . $obraId . '#planejamento');
    }

    public function updateComposition(Request $request, string $id, string $resourceId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $obraId = (int) $id;
        $type = $this->option($request, 'resource_type', ['Material', 'Servico', 'Equipamento'], 'Material');
        $repository = new ObraRepository();
        $existing = $repository->findCompositionResource($obraId, $type, (int) $resourceId);
        if (!$existing) {
            $_SESSION['flash_error'] = 'Recurso da composicao nao encontrado.';
            redirect('/obras/' . $obraId . '#planejamento');
        }
        $catalogRepository = new SinapiRepository();
        $selectedCatalogId = (int) $request->input('sinapi_id', 0);
        $catalogItem = $catalogRepository->findForType($selectedCatalogId, $type);
        if (!$catalogItem && $selectedCatalogId > 0 && $selectedCatalogId === (int) ($existing['sinapi_id'] ?? 0)) {
            $catalogItem = $catalogRepository->findReference($selectedCatalogId, $type);
        }
        $catalogItem ??= [
            'id' => $existing['sinapi_id'] ?: null,
            'descricao' => $existing['nome'],
            'unidade' => $existing['unidade'],
            'preco_mg' => $existing['valor_unitario'],
        ];
        $repository->updateCompositionResource($obraId, $type, (int) $resourceId, [
            'item_id' => (int) $existing['item_id'],
            'fornecedor_id' => $repository->createFornecedorIfNeeded((string) $request->input('fornecedor_nome')),
            'sinapi_id' => !empty($catalogItem['id']) ? (int) $catalogItem['id'] : null,
            'nome' => (string) $catalogItem['descricao'],
            'unidade' => (string) $catalogItem['unidade'],
            'quantidade' => max(0, (float) $this->money($request->input('quantidade', '1'))),
            'valor_unitario' => (float) ($catalogItem['preco_mg'] ?? 0),
            'status' => $this->resourceStatus($request, $type),
            'observacoes' => trim((string) $request->input('observacoes')),
        ]);
        $_SESSION['flash_success'] = 'Recurso da composicao atualizado.';
        redirect('/obras/' . $obraId . '#planejamento');
    }

    public function destroyComposition(Request $request, string $id, string $resourceId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $obraId = (int) $id;
        $type = $this->option($request, 'resource_type', ['Material', 'Servico', 'Equipamento'], 'Material');
        $deleted = (new ObraRepository())->deleteCompositionResource($obraId, $type, (int) $resourceId);
        $_SESSION[$deleted ? 'flash_success' : 'flash_error'] = $deleted
            ? 'Recurso removido da composicao.'
            : 'Recurso da composicao nao encontrado.';
        redirect('/obras/' . $obraId . '#planejamento');
    }

    public function storePlanningModel(Request $request, string $id): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $name = trim((string) $request->input('nome'));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Informe o nome do modelo.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        try {
            (new ObraRepository())->createPlanningModel((int) $id, $name, trim((string) $request->input('descricao')), (int) $user['id']);
            $_SESSION['flash_success'] = 'Planejamento salvo como modelo.';
        } catch (\Throwable $exception) {
            $_SESSION['flash_error'] = $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Ja existe um modelo com esse nome.';
        }
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function exportPlanning(Request $request, string $id): void
    {
        Auth::requireLogin();
        $repository = new ObraRepository();
        $obra = $repository->find((int) $id);
        if (!$obra) {
            http_response_code(404);
            exit('Obra nao encontrada.');
        }
        $rows = $this->planningExportRows($repository->planningItems((int) $id));
        $format = strtolower((string) $request->input('formato', 'xlsx'));
        $filename = 'planejamento-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $obra['nome']));
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            foreach ($rows as $row) fputcsv($output, $row, ';');
            fclose($output);
            return;
        }
        if ($format === 'pdf') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            echo SimplePdf::create('Planejamento - ' . $obra['nome'], array_map(static fn (array $row): array => array_slice($row, 0, 8), $rows));
            return;
        }
        $path = Spreadsheet::createXlsx($rows);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        unlink($path);
    }

    public function importPlanning(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $file = $_FILES['arquivo'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Selecione um arquivo XLSX ou CSV de ate 10 MB.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
            $_SESSION['flash_error'] = 'Formato nao suportado. Use XLS, XLSX ou CSV.';
            redirect('/obras/' . (int) $id . '#planejamento');
        }
        try {
            $rows = Spreadsheet::read((string) $file['tmp_name'], (string) $file['name']);
            $items = $this->planningImportItems($rows);
            $result = (new ObraRepository())->importAnalyticalStructure((int) $id, $items);
            $_SESSION['flash_success'] = $result['items'] . ' itens importados para o planejamento.';
        } catch (\Throwable $exception) {
            $_SESSION['flash_error'] = $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Nao foi possivel importar a planilha.';
        }
        redirect('/obras/' . (int) $id . '#planejamento');
    }

    public function storeMovement(Request $request, string $id): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $repository = new ObraRepository();
        $data = $this->movementData($request);
        $data['fornecedor_id'] = $repository->createFornecedorIfNeeded((string) $request->input('fornecedor_nome'));
        if ($data['descricao'] === '' || $data['data_movimento'] === '') {
            $_SESSION['flash_error'] = 'Informe descricao e data do movimento.';
            redirect('/obras/' . (int) $id . '#financeiro');
        }
        $repository->createMovement((int) $id, $data, (int) $user['id']);
        $_SESSION['flash_success'] = 'Movimento financeiro registrado.';
        redirect('/obras/' . (int) $id . '#financeiro');
    }

    public function storeDiary(Request $request, string $id): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $data = [
            'data_registro' => (string) $request->input('data_registro', date('Y-m-d')),
            'titulo' => trim((string) $request->input('titulo')),
            'relato' => trim((string) $request->input('relato')),
            'clima' => trim((string) $request->input('clima')),
            'equipe_presente' => trim((string) $request->input('equipe_presente')),
            'impedimentos' => trim((string) $request->input('impedimentos')),
        ];
        if ($data['titulo'] === '' || $data['relato'] === '') {
            $_SESSION['flash_error'] = 'Informe titulo e relato do diario.';
            redirect('/obras/' . (int) $id . '#diario');
        }
        (new ObraRepository())->createDiary((int) $id, $data, (int) $user['id']);
        $_SESSION['flash_success'] = 'Diario registrado.';
        redirect('/obras/' . (int) $id . '#diario');
    }

    private function obraData(Request $request): array
    {
        return [
            'nome' => trim((string) $request->input('nome')),
            'local' => trim((string) $request->input('local')),
            'descricao' => trim((string) $request->input('descricao')),
            'status' => $this->option($request, 'status', ['Planejada', 'Em andamento', 'Pausada', 'Concluida', 'Cancelada'], 'Planejada'),
            'prioridade' => $this->option($request, 'prioridade', ['Baixa', 'Media', 'Alta', 'Critica'], 'Media'),
            'data_inicio_prevista' => (string) $request->input('data_inicio_prevista'),
            'data_fim_prevista' => (string) $request->input('data_fim_prevista'),
        ];
    }

    private function itemData(Request $request): array
    {
        return [
            'parent_id' => (int) $request->input('parent_id', 0),
            'predecessor_ids' => $request->input('predecessor_ids', []),
            'nome' => trim((string) $request->input('nome')),
            'descricao' => trim((string) $request->input('descricao')),
            'responsavel' => trim((string) $request->input('responsavel')),
            'ordem' => (string) $request->input('ordem', '0'),
            'status' => $this->option($request, 'status', ['Nao iniciada', 'Em andamento', 'Bloqueada', 'Pausada', 'Concluida', 'Cancelada'], 'Nao iniciada'),
            'progresso' => (string) $request->input('progresso', '0'),
            'duracao_prevista_dias' => (string) $request->input('duracao_prevista_dias'),
            'data_inicio_prevista' => (string) $request->input('data_inicio_prevista'),
            'data_fim_prevista' => (string) $request->input('data_fim_prevista'),
            'custo_previsto' => $this->money($request->input('custo_previsto')),
            'marco' => (int) $request->input('marco', 0),
        ];
    }

    private function memorialData(Request $request): array
    {
        $option = fn(string $key,array $allowed,string $default): string => $this->option($request,$key,$allowed,$default);
        return [
            'ativo'=>(int)$request->input('ativo',0),
            'tipo_obra'=>$option('tipo_obra',['Residencial','Comercial','Industrial','Institucional','Reforma','Infraestrutura','Outro'],'Comercial'),
            'padrao_acabamento'=>$option('padrao_acabamento',['Economico','Padrao','Alto','Especial'],'Padrao'),
            'sistema_estrutural'=>$option('sistema_estrutural',['Concreto armado','Estrutura metalica','Alvenaria estrutural','Misto','Outro'],'Concreto armado'),
            'area_terreno'=>max(0,(float)$this->money($request->input('area_terreno'))),
            'area_construida'=>max(0,(float)$this->money($request->input('area_construida'))),
            'testada'=>max(0,(float)$this->money($request->input('testada'))),
            'pavimentos'=>max(1,(int)$request->input('pavimentos',1)),
            'unidades'=>max(1,(int)$request->input('unidades',1)),
            'banheiros'=>max(0,(int)$request->input('banheiros',1)),
            'cozinhas'=>max(0,(int)$request->input('cozinhas',1)),
            'pe_direito'=>max(2,(float)$this->money($request->input('pe_direito','2.8'))),
            'area_externa'=>max(0,(float)$this->money($request->input('area_externa'))),
            'area_cobertura'=>max(0,(float)$this->money($request->input('area_cobertura'))),
            'meses_obra'=>max(1,(int)$request->input('meses_obra',12)),
            'possui_subsolo'=>(int)$request->input('possui_subsolo',0),
            'possui_elevador'=>(int)$request->input('possui_elevador',0),
            'possui_piscina'=>(int)$request->input('possui_piscina',0),
            'comprimento_baldrame'=>$this->nonNegativeNumber($request,'comprimento_baldrame'),
            'comprimento_muro_divisa'=>$this->nonNegativeNumber($request,'comprimento_muro_divisa'),
            'quantidade_brocas'=>$this->nonNegativeNumber($request,'quantidade_brocas'),
            'peso_aco_estimado'=>$this->nonNegativeNumber($request,'peso_aco_estimado'),
            'volume_concreto_estimado'=>$this->nonNegativeNumber($request,'volume_concreto_estimado'),
            'area_revestimento_interno'=>$this->nonNegativeNumber($request,'area_revestimento_interno'),
            'area_revestimento_externo'=>$this->nonNegativeNumber($request,'area_revestimento_externo'),
            'area_reboco'=>$this->nonNegativeNumber($request,'area_reboco'),
            'quantidade_portas_madeira'=>$this->nonNegativeNumber($request,'quantidade_portas_madeira'),
            'quantidade_esquadrias_aluminio'=>$this->nonNegativeNumber($request,'quantidade_esquadrias_aluminio'),
            'guarda_corpo_m'=>$this->nonNegativeNumber($request,'guarda_corpo_m'),
            'quantidade_bancadas'=>$this->nonNegativeNumber($request,'quantidade_bancadas'),
            'soleiras_m'=>$this->nonNegativeNumber($request,'soleiras_m'),
            'peitoris_m'=>$this->nonNegativeNumber($request,'peitoris_m'),
            'area_forro_gesso'=>$this->nonNegativeNumber($request,'area_forro_gesso'),
            'area_forro_madeira'=>$this->nonNegativeNumber($request,'area_forro_madeira'),
            'area_telhado_metalico'=>$this->nonNegativeNumber($request,'area_telhado_metalico'),
            'area_telhado_ceramico'=>$this->nonNegativeNumber($request,'area_telhado_ceramico'),
            'calhas_m'=>$this->nonNegativeNumber($request,'calhas_m'),
            'rufos_m'=>$this->nonNegativeNumber($request,'rufos_m'),
            'area_pintura_interna'=>$this->nonNegativeNumber($request,'area_pintura_interna'),
            'area_pintura_externa'=>$this->nonNegativeNumber($request,'area_pintura_externa'),
            'quantidade_bacias'=>$this->nonNegativeNumber($request,'quantidade_bacias'),
            'quantidade_torneiras'=>$this->nonNegativeNumber($request,'quantidade_torneiras'),
            'quantidade_cubas'=>$this->nonNegativeNumber($request,'quantidade_cubas'),
            'area_deck'=>$this->nonNegativeNumber($request,'area_deck'),
            'area_paisagismo'=>$this->nonNegativeNumber($request,'area_paisagismo'),
            'area_piscina'=>$this->nonNegativeNumber($request,'area_piscina'),
            'pontos_eletricos'=>$this->nonNegativeNumber($request,'pontos_eletricos'),
            'pontos_hidraulicos'=>$this->nonNegativeNumber($request,'pontos_hidraulicos'),
            'observacoes'=>trim((string)$request->input('observacoes')) ?: null,
        ];
    }

    private function nonNegativeNumber(Request $request, string $key): float
    {
        return max(0, (float) $this->money($request->input($key)));
    }

    private function syncManualMemorialLink(int $obraId, int $itemId, Request $request): void
    {
        $repository=new MemorialRepository();
        $memorial=$repository->findByObra($obraId);
        if (!$memorial || !(int)$memorial['ativo']) return;
        $existing=$repository->linkedItem($obraId,$itemId);
        if ((int)$request->input('memorial_link',0) === 1) {
            $repository->link((int)$memorial['id'],$itemId,$existing['regra_codigo'] ?? 'manual.'.$itemId,'Associado',1,'item','Associacao manual confirmada pelo usuario');
        } elseif ($existing && $existing['origem'] === 'Associado') {
            $repository->detachItem($itemId);
        }
    }

    private function planningCalendar(string $month, array $items): array
    {
        $first=strtotime($month.'-01'); $events=[];
        foreach ($items as $item) if (!empty($item['data_inicio_prevista'])) $events[$item['data_inicio_prevista']][]=$item;
        $days=array_fill(0,(int)date('w',$first),null);
        for($day=1;$day<=(int)date('t',$first);$day++) { $date=sprintf('%s-%02d',$month,$day); $days[]=['date'=>$date,'day'=>$day,'events'=>$events[$date]??[]]; }
        while(count($days)%7!==0)$days[]=null;
        return $days;
    }

    private function ganttData(array $items): array
    {
        $dated=array_values(array_filter($items,static fn(array $item): bool => !empty($item['data_inicio_prevista']) && !empty($item['data_fim_prevista'])));
        if ($dated===[]) return ['items'=>[],'days'=>1,'start'=>null,'finish'=>null];
        $starts=array_column($dated,'data_inicio_prevista'); $finishes=array_column($dated,'data_fim_prevista');
        $start=new \DateTimeImmutable(min($starts)); $finish=new \DateTimeImmutable(max($finishes));
        $days=max(1,(int)$start->diff($finish)->format('%a')+1);
        foreach($dated as &$item) {
            $itemStart=new \DateTimeImmutable($item['data_inicio_prevista']); $itemFinish=new \DateTimeImmutable($item['data_fim_prevista']);
            $item['gantt_left']=((int)$start->diff($itemStart)->format('%a')/$days)*100;
            $item['gantt_width']=max(0.7,(((int)$itemStart->diff($itemFinish)->format('%a')+1)/$days)*100);
        }
        unset($item);
        return ['items'=>$dated,'days'=>$days,'start'=>$start->format('Y-m-d'),'finish'=>$finish->format('Y-m-d')];
    }

    private function icsText(string $value): string
    {
        return str_replace(["\\",";",",","\r","\n"],["\\\\","\\;","\\,",'',"\\n"],$value);
    }

    private function materialData(Request $request): array
    {
        return [
            'item_id' => (int) $request->input('item_id', 0),
            'nome' => trim((string) $request->input('nome')),
            'especificacao' => trim((string) $request->input('especificacao')),
            'unidade' => trim((string) $request->input('unidade', 'un')),
            'quantidade_prevista' => $this->money($request->input('quantidade_prevista')),
            'quantidade_comprada' => $this->money($request->input('quantidade_comprada')),
            'valor_unitario_previsto' => $this->money($request->input('valor_unitario_previsto')),
            'valor_unitario_real' => $this->money($request->input('valor_unitario_real')),
            'status' => $this->option($request, 'status', ['Previsto', 'Solicitado', 'Comprado', 'Entregue', 'Cancelado'], 'Previsto'),
            'data_necessidade' => (string) $request->input('data_necessidade'),
            'observacoes' => trim((string) $request->input('observacoes')),
        ];
    }

    private function serviceData(Request $request): array
    {
        return [
            'item_id' => (int) $request->input('item_id', 0),
            'nome' => trim((string) $request->input('nome')),
            'escopo' => trim((string) $request->input('escopo')),
            'unidade' => trim((string) $request->input('unidade', 'servico')),
            'quantidade' => $this->money($request->input('quantidade', '1')),
            'valor_previsto' => $this->money($request->input('valor_previsto')),
            'valor_contratado' => $this->money($request->input('valor_contratado')),
            'status' => $this->option($request, 'status', ['Previsto', 'Cotando', 'Contratado', 'Em execucao', 'Medido', 'Concluido', 'Cancelado'], 'Previsto'),
            'data_inicio' => (string) $request->input('data_inicio'),
            'data_fim' => (string) $request->input('data_fim'),
            'observacoes' => trim((string) $request->input('observacoes')),
        ];
    }

    private function equipmentData(Request $request): array
    {
        return [
            'item_id' => (int) $request->input('item_id', 0),
            'nome' => '',
            'unidade' => '',
            'quantidade_prevista' => $this->money($request->input('quantidade_prevista')),
            'quantidade_real' => $this->money($request->input('quantidade_real')),
            'valor_unitario_previsto' => '0',
            'valor_unitario_real' => $this->money($request->input('valor_unitario_real')),
            'status' => $this->option($request, 'status', ['Previsto', 'Cotando', 'Contratado', 'Em uso', 'Devolvido', 'Cancelado'], 'Previsto'),
            'data_inicio' => (string) $request->input('data_inicio'),
            'data_fim' => (string) $request->input('data_fim'),
            'observacoes' => trim((string) $request->input('observacoes')),
        ];
    }

    private function movementData(Request $request): array
    {
        return [
            'item_id' => (int) $request->input('item_id', 0),
            'tipo' => $this->option($request, 'tipo', ['Entrada', 'Saida'], 'Saida'),
            'categoria' => $this->option($request, 'categoria', ['Material', 'Servico', 'Equipamento', 'Mao de obra', 'Taxa', 'Doacao', 'Outro'], 'Outro'),
            'descricao' => trim((string) $request->input('descricao')),
            'valor' => $this->money($request->input('valor')),
            'data_movimento' => (string) $request->input('data_movimento', date('Y-m-d')),
            'vencimento' => (string) $request->input('vencimento'),
            'status' => $this->option($request, 'status', ['Previsto', 'Comprometido', 'Pago', 'Recebido', 'Cancelado'], 'Previsto'),
            'forma_pagamento' => trim((string) $request->input('forma_pagamento')),
            'documento' => trim((string) $request->input('documento')),
            'observacoes' => trim((string) $request->input('observacoes')),
        ];
    }

    private function option(Request $request, string $key, array $allowed, string $default): string
    {
        $value = (string) $request->input($key, $default);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function resourceStatus(Request $request, string $type): string
    {
        $statuses = [
            'Material' => ['Previsto', 'Solicitado', 'Comprado', 'Entregue', 'Cancelado'],
            'Servico' => ['Previsto', 'Cotando', 'Contratado', 'Em execucao', 'Medido', 'Concluido', 'Cancelado'],
            'Equipamento' => ['Previsto', 'Cotando', 'Contratado', 'Em uso', 'Devolvido', 'Cancelado'],
        ];
        return $this->option($request, 'status', $statuses[$type] ?? ['Previsto'], 'Previsto');
    }

    private function planningExportRows(array $items): array
    {
        $rows = [['EAP', 'Item', 'EAP superior', 'Descricao', 'Responsavel', 'Status', 'Progresso (%)', 'Duracao (dias)', 'Inicio previsto', 'Termino previsto', 'Custo previsto', 'Marco', 'Predecessoras']];
        $wbsById = [];
        foreach ($items as $item) $wbsById[(int) $item['id']] = $item['wbs'];
        foreach ($items as $item) {
            $rows[] = [
                $item['wbs'], $item['nome'], !empty($item['parent_id']) ? ($wbsById[(int) $item['parent_id']] ?? '') : '',
                $item['descricao'] ?? '', $item['responsavel'] ?? '', $item['status'], $item['progresso'],
                $item['duracao_prevista_dias'] ?? '', $item['data_inicio_prevista'] ?? '', $item['data_fim_prevista'] ?? '',
                $item['custo_previsto'], !empty($item['marco']) ? 'Sim' : 'Nao', implode(', ', array_map(static fn (int $id): string => $wbsById[$id] ?? '', $item['predecessor_ids'])),
            ];
        }
        return $rows;
    }

    private function planningImportItems(array $rows): array
    {
        if (count($rows) < 2) throw new \InvalidArgumentException('A planilha nao possui itens para importar.');
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value), 'UTF-8');
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            return preg_replace('/[^a-z0-9]+/', '_', $ascii ?: $value) ?: '';
        };
        $headers = array_map($normalize, array_shift($rows));
        $index = array_flip($headers);
        if (!isset($index['eap'], $index['item'])) throw new \InvalidArgumentException('A planilha deve conter as colunas EAP e Item.');
        $items = [];
        $statuses = ['Nao iniciada', 'Em andamento', 'Bloqueada', 'Pausada', 'Concluida', 'Cancelada'];
        $cell = static fn (array $row, array $index, string $key): string => trim((string) ($row[$index[$key] ?? -1] ?? ''));
        $date = static function (string $value): string {
            if ($value === '') return '';
            if (is_numeric($value)) return gmdate('Y-m-d', ((int) $value - 25569) * 86400);
            $timestamp = strtotime(str_replace('/', '-', $value));
            return $timestamp ? date('Y-m-d', $timestamp) : '';
        };
        foreach ($rows as $position => $row) {
            $code = trim((string) ($row[$index['eap']] ?? ''));
            $name = trim((string) ($row[$index['item']] ?? ''));
            if ($code === '' || $name === '') continue;
            $parent = trim((string) ($row[$index['eap_superior'] ?? -1] ?? ''));
            if ($parent === '' && str_contains($code, '.')) $parent = substr($code, 0, (int) strrpos($code, '.'));
            $status = $cell($row, $index, 'status');
            $progress = (int) preg_replace('/[^0-9]/', '', $cell($row, $index, 'progresso'));
            if ($progress === 0) $progress = (int) preg_replace('/[^0-9]/', '', $cell($row, $index, 'progresso_'));
            $duration = $cell($row, $index, 'duracao_dias');
            $costText = $cell($row, $index, 'custo_previsto');
            $cost = str_contains($costText, ',') ? str_replace(['.', ','], ['', '.'], $costText) : $costText;
            $predecessors = array_values(array_filter(array_map('trim', explode(',', $cell($row, $index, 'predecessoras')))));
            $items[] = [
                'code' => $code, 'parent_code' => $parent !== '' ? $parent : null, 'name' => $name,
                'description' => $cell($row, $index, 'descricao'), 'responsible' => $cell($row, $index, 'responsavel'),
                'status' => in_array($status, $statuses, true) ? $status : 'Nao iniciada', 'progress' => min(100, max(0, $progress)),
                'duration' => $duration !== '' ? (int) $duration : '', 'start' => $date($cell($row, $index, 'inicio_previsto')),
                'finish' => $date($cell($row, $index, 'termino_previsto')), 'cost' => is_numeric($cost) ? $cost : 0,
                'milestone' => mb_strtolower($cell($row, $index, 'marco')) === 'sim' ? 1 : 0,
                'predecessor_codes' => $predecessors, 'order' => ($position + 1) * 10,
            ];
        }
        if ($items === []) throw new \InvalidArgumentException('Nenhum item valido foi encontrado na planilha.');
        return $items;
    }

    private function applyCatalogItem(array $data, array $catalogItem, ?string $priceField = null): array
    {
        $data['sinapi_id'] = (int) $catalogItem['id'];
        $data['nome'] = (string) $catalogItem['descricao'];
        $data['unidade'] = (string) $catalogItem['unidade'];
        if ($priceField !== null) {
            $data[$priceField] = (string) ($catalogItem['preco_mg'] ?? 0);
        }

        return $data;
    }

    private function money(mixed $value): string
    {
        return str_replace(',', '.', trim((string) ($value ?? '0')));
    }
}
