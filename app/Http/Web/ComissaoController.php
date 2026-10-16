<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use DateTimeImmutable;
use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\Response;
use IasdCentral\Core\View;
use IasdCentral\Repositories\ComissaoRepository;
use IasdCentral\Repositories\MenuRepository;

final class ComissaoController
{
    private const SITUATIONS = ['aguardando', 'iniciada', 'finalizada'];
    private const OFFICE_STATUSES = ['ativo', 'inativo'];
    private const VOTE_ROLES = ['votado', 'associado', 'candidato', 'secretario'];
    private const COMMISSION_MEMBER_ROLES = ['presidente', 'secretario', 'membro'];

    public function index(Request $request): void
    {
        Auth::requireLogin();
        redirect('/comissao/comissoes');
    }

    public function people(Request $request): void
    {
        $this->render('pessoas');
    }

    public function editPerson(Request $request, string $id): void
    {
        $repository = new ComissaoRepository();
        $record = $repository->findPerson((int) $id);
        if ($record === null) {
            $this->notFound('Pessoa não encontrada.', '/comissao/pessoas');
        }

        $this->render('pessoas', $record);
    }

    public function storePerson(Request $request): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $data = $this->personData($request);
        $error = $this->validatePerson($data);
        if ($error !== null) {
            $this->fail($error, '/comissao/pessoas');
        }

        (new ComissaoRepository())->createPerson($data);
        $this->success('Pessoa cadastrada.', '/comissao/pessoas');
    }

    public function updatePerson(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $personId = (int) $id;
        $repository = new ComissaoRepository();
        if ($repository->findPerson($personId) === null) {
            $this->notFound('Pessoa não encontrada.', '/comissao/pessoas');
        }

        $data = $this->personData($request);
        $error = $this->validatePerson($data, $personId);
        if ($error !== null) {
            $this->fail($error, '/comissao/pessoas/' . $personId . '/editar');
        }

        $repository->updatePerson($personId, $data);
        $this->success('Pessoa atualizada.', '/comissao/pessoas');
    }

    public function destroyPerson(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        (new ComissaoRepository())->deletePerson((int) $id);
        $this->success('Pessoa removida.', '/comissao/pessoas');
    }

    public function commissions(Request $request): void
    {
        $this->render('comissoes');
    }

    public function editCommission(Request $request, string $id): void
    {
        $repository = new ComissaoRepository();
        $record = $repository->findCommission((int) $id);
        if ($record === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }

        $this->render('comissoes', $record);
    }

    public function storeCommission(Request $request): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $data = $this->commissionData($request);
        $error = $this->validateCommission($data);
        if ($error !== null) {
            $this->fail($error, '/comissao/comissoes');
        }

        (new ComissaoRepository())->createCommission($data);
        $this->success('Comissão cadastrada.', '/comissao/comissoes');
    }

    public function updateCommission(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        if ($repository->findCommission($commissionId) === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }

        $data = $this->commissionData($request);
        $error = $this->validateCommission($data);
        if ($error !== null) {
            $this->fail($error, '/comissao/comissoes/' . $commissionId . '/editar');
        }

        $repository->updateCommission($commissionId, $data);
        $this->success('Comissão atualizada.', '/comissao/comissoes');
    }

    public function destroyCommission(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        (new ComissaoRepository())->deleteCommission((int) $id);
        $this->success('Comissão removida.', '/comissao/comissoes');
    }

    public function startCommission(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] === 'finalizada') {
            $this->fail('Uma comissão finalizada não pode ser iniciada novamente.', '/comissao/comissoes');
        }
        if (!$repository->startCommission($commissionId)) {
            $this->fail('Não foi possível iniciar a comissão.', '/comissao/comissoes');
        }

        $this->success('Comissão iniciada e cargos vinculados.', '/comissao/comissoes/' . $commissionId . '/cargos');
    }

    public function commissionOffices(Request $request, string $id): void
    {
        $user = Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] === 'aguardando') {
            $this->fail('Use o botão Iniciar para preparar os cargos desta comissão.', '/comissao/comissoes');
        }

        $selectedVote = null;
        $voteFlow = null;
        $voteParticipants = [];
        $availablePeople = [];
        $indicationOverview = [
            'names' => [],
            'missing_indications' => [],
            'missing_votes' => [],
            'vote_open' => false,
        ];
        $selectedVoteId = (int) $request->input('votacao', 0);
        if ($selectedVoteId > 0) {
            $selectedVote = $repository->findCommissionOffice($commissionId, $selectedVoteId);
            if ($selectedVote === null) {
                $this->notFound('Votação não encontrada nesta comissão.', '/comissao/comissoes/' . $commissionId . '/cargos');
            }
            $voteFlow = $repository->findVoteFlow($selectedVoteId);
            $voteParticipants = $repository->voteParticipants($selectedVoteId);
            $indicationOverview = $repository->indicationOverview($selectedVoteId);
            if ($commission['situacao'] === 'iniciada' && $selectedVote['votacao_status'] === 'aberta') {
                $availablePeople = $repository->availablePeopleForVote($selectedVoteId);
            }
        }

        View::render('modules/comissao/cargos-comissao', [
            'title' => 'Cargos da comissão',
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'commission' => $commission,
            'offices' => $repository->commissionOffices($commissionId),
            'availableOffices' => $repository->availableOffices($commissionId),
            'commissionMembers' => $repository->commissionMembers($commissionId),
            'availableCommissionPeople' => $repository->availablePeopleForCommission($commissionId),
            'editable' => $commission['situacao'] === 'iniciada',
            'membersDialogOpen' => (string) $request->input('membros', '') === '1',
            'selectedVote' => $selectedVote,
            'voteFlow' => $voteFlow,
            'voteParticipants' => $voteParticipants,
            'availablePeople' => $availablePeople,
            'indicationOverview' => $indicationOverview,
        ]);
    }

    public function votingDisplay(Request $request, string $id): void
    {
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }

        $presentation = $repository->currentCommissionPresentation($commissionId);
        $items = [];
        if ($presentation !== null) {
            $items = match ($presentation['display_mode']) {
                'indications' => $repository->distinctIndications((int) $presentation['voto_id']),
                'voting' => $repository->memberVotingCandidates((int) $presentation['voto_id']),
                'result' => $repository->voteParticipants((int) $presentation['comissao_cargo_id']),
            };
        }

        View::render('modules/comissao/voting-display', [
            'title' => 'Tela de votação',
            'commission' => $commission,
            'presentation' => $presentation,
            'items' => $items,
            'displayMode' => true,
        ], 'layouts/member');
    }

    public function indicationStatus(Request $request, string $id, string $voteId): void
    {
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        $repository = new ComissaoRepository();
        if ($repository->findCommissionOffice($commissionId, $commissionOfficeId) === null) {
            Response::json(['ok' => false, 'message' => 'Votação não encontrada.'], 404);
            return;
        }

        Response::json(['ok' => true] + $repository->indicationOverview($commissionOfficeId));
    }

    public function addCommissionOffice(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar seus cargos.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }

        $newOffice = trim((string) $request->input('novo_cargo'));
        $officeId = (int) $request->input('cargo_id', 0);
        if ($newOffice !== '') {
            if (mb_strlen($newOffice) > 180) {
                $this->fail('Informe o nome do cargo com até 180 caracteres.', '/comissao/comissoes/' . $commissionId . '/cargos');
            }
            $repository->createAndAttachOffice($commissionId, $newOffice);
        } else {
            $office = $repository->findOffice($officeId);
            if ($office === null || $office['status'] !== 'ativo') {
                $this->fail('Selecione um cargo existente ou informe um novo cargo.', '/comissao/comissoes/' . $commissionId . '/cargos');
            }
            $repository->attachOffice($commissionId, $officeId);
        }

        $this->success('Cargo adicionado à comissão.', '/comissao/comissoes/' . $commissionId . '/cargos');
    }

    public function removeCommissionOffice(Request $request, string $id, string $cargoId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar seus cargos.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }

        $commissionOffice = $repository->findCommissionOfficeByCargo($commissionId, (int) $cargoId);
        if ($commissionOffice !== null && $commissionOffice['votacao_status'] === 'encerrada') {
            $this->fail('Reabra a votação antes de retirar este cargo.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }

        $repository->detachOffice($commissionId, (int) $cargoId);
        $this->success('Cargo retirado desta comissão.', '/comissao/comissoes/' . $commissionId . '/cargos');
    }

    public function moveCommissionOffice(Request $request, string $id, string $cargoId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $direction = (string) $request->input('direcao');
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar a ordem dos cargos.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }
        if (!in_array($direction, ['up', 'down'], true)) {
            $this->fail('Direção inválida para mover o cargo.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }

        $repository->moveCommissionOffice($commissionId, (int) $cargoId, $direction);
        redirect('/comissao/comissoes/' . $commissionId . '/cargos');
    }

    public function addCommissionMember(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar seus membros.', $this->membersPath($commissionId));
        }

        $role = (string) $request->input('papel', 'membro');
        if (!in_array($role, self::COMMISSION_MEMBER_ROLES, true)) {
            $this->fail('Selecione uma classificação válida.', $this->membersPath($commissionId));
        }

        $newPerson = trim((string) $request->input('nova_pessoa'));
        $personId = (int) $request->input('pessoa_id', 0);
        if ($newPerson !== '') {
            if (mb_strlen($newPerson) > 160) {
                $this->fail('Informe o nome da pessoa com até 160 caracteres.', $this->membersPath($commissionId));
            }
            $repository->createAndAttachCommissionMember($commissionId, $newPerson, $role);
        } else {
            if ($personId < 1 || $repository->findPerson($personId) === null) {
                $this->fail('Selecione uma pessoa existente ou informe um novo nome.', $this->membersPath($commissionId));
            }
            $repository->attachCommissionMember($commissionId, $personId, $role);
        }

        $this->success('Membro adicionado à comissão.', $this->membersPath($commissionId));
    }

    public function updateCommissionMember(Request $request, string $id, string $memberId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar seus membros.', $this->membersPath($commissionId));
        }

        $role = (string) $request->input('papel');
        if (!in_array($role, self::COMMISSION_MEMBER_ROLES, true)) {
            $this->fail('Selecione uma classificação válida.', $this->membersPath($commissionId));
        }

        $repository->updateCommissionMember($commissionId, (int) $memberId, $role);
        $this->success('Classificação do membro atualizada.', $this->membersPath($commissionId));
    }

    public function removeCommissionMember(Request $request, string $id, string $memberId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar seus membros.', $this->membersPath($commissionId));
        }

        $repository->removeCommissionMember($commissionId, (int) $memberId);
        $this->success('Membro retirado da comissão.', $this->membersPath($commissionId));
    }

    public function addVotePerson(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Reabra a votação antes de adicionar pessoas.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $newPerson = trim((string) $request->input('nova_pessoa'));
        $personId = (int) $request->input('pessoa_id', 0);
        if ($newPerson !== '') {
            if (mb_strlen($newPerson) > 160) {
                $this->fail('Informe o nome da pessoa com até 160 caracteres.', $this->votePath($commissionId, $commissionOfficeId));
            }
            $repository->createAndAttachPersonToVote($commissionOfficeId, $newPerson);
        } else {
            if ($personId < 1 || $repository->findPerson($personId) === null) {
                $this->fail('Selecione uma pessoa existente ou informe um novo nome.', $this->votePath($commissionId, $commissionOfficeId));
            }
            $repository->attachPersonToVote($commissionOfficeId, $personId);
        }

        $this->success('Pessoa adicionada à votação.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function openIndications(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Reabra a votação antes de abrir as indicações.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $rawLimit = trim((string) $request->input('limite_indicacoes'));
        if (!preg_match('/^[1-9]$/', $rawLimit)) {
            $this->fail('Informe um limite de indicações entre 1 e 9.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $rawVoteLimit = trim((string) $request->input('limite_votos'));
        if (!preg_match('/^[1-9][0-9]?$/', $rawVoteLimit)) {
            $this->fail('Informe uma quantidade de votos entre 1 e 99.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->openIndications($commissionOfficeId, (int) $rawLimit, (int) $rawVoteLimit);
        $this->success(
            'Indicações abertas com limite de ' . $rawLimit . ' indicação(ões) e '
                . $rawVoteLimit . ' voto(s) por membro.',
            $this->votePath($commissionId, $commissionOfficeId)
        );
    }

    public function closeIndications(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Esta votação já está encerrada.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $flow = $repository->findVoteFlow($commissionOfficeId);
        if ($flow === null || $flow['status'] !== 'indicacao') {
            $this->fail('As indicações não estão abertas.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->closeIndications($commissionOfficeId);
        $this->success('Indicações encerradas.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function openVoting(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Reabra esta votação antes de iniciar uma nova etapa.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $flow = $repository->findVoteFlow($commissionOfficeId);
        if ($flow === null || $flow['status'] !== 'encerrado') {
            $this->fail('Encerre as indicações antes de abrir a votação.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->openVoting($commissionOfficeId);
        $this->success('Votação aberta.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function cancelVoting(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Reabra esta votação antes de cancelá-la.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $flow = $repository->findVoteFlow($commissionOfficeId);
        if ($flow === null || $flow['status'] !== 'votando') {
            $this->fail('Não há uma votação aberta para cancelar.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->cancelVoting($commissionOfficeId);
        $this->success('Votação cancelada. O cargo voltou para Aguardando.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function saveVote(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Esta votação já está encerrada.', $this->votePath($commissionId, $commissionOfficeId));
        }
        $flow = $repository->findVoteFlow($commissionOfficeId);
        if ($flow === null || $flow['status'] !== 'votando') {
            $this->fail('Abra a votação antes de registrar os votos.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->saveVoteParticipants($commissionOfficeId, $this->voteEntries($request, $commissionId, $commissionOfficeId));
        $this->success('Votação salva.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function closeVote(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'aberta') {
            $this->fail('Esta votação já está encerrada.', $this->votePath($commissionId, $commissionOfficeId));
        }
        $flow = $repository->findVoteFlow($commissionOfficeId);
        if ($flow === null || $flow['status'] !== 'votando') {
            $this->fail('Abra a votação antes de encerrá-la.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->saveVoteParticipants($commissionOfficeId, $this->voteEntries($request, $commissionId, $commissionOfficeId));
        $repository->closeVote($commissionOfficeId);
        $this->success('Votação encerrada.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function reopenVote(Request $request, string $id, string $voteId): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $commissionId = (int) $id;
        $commissionOfficeId = (int) $voteId;
        [$repository, , $vote] = $this->editableVote($commissionId, $commissionOfficeId);
        if ($vote['votacao_status'] !== 'encerrada') {
            $this->fail('Esta votação já está aberta.', $this->votePath($commissionId, $commissionOfficeId));
        }

        $repository->reopenVote($commissionOfficeId);
        $this->success('Votação reaberta com os dados anteriores.', $this->votePath($commissionId, $commissionOfficeId));
    }

    public function offices(Request $request): void
    {
        $this->render('cargos');
    }

    public function editOffice(Request $request, string $id): void
    {
        $repository = new ComissaoRepository();
        $record = $repository->findOffice((int) $id);
        if ($record === null) {
            $this->notFound('Cargo não encontrado.', '/comissao/cargos');
        }

        $this->render('cargos', $record);
    }

    public function storeOffice(Request $request): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $data = $this->officeData($request);
        $error = $this->validateOffice($data);
        if ($error !== null) {
            $this->fail($error, '/comissao/cargos');
        }

        (new ComissaoRepository())->createOffice($data);
        $this->success('Cargo cadastrado.', '/comissao/cargos');
    }

    public function updateOffice(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        $officeId = (int) $id;
        $repository = new ComissaoRepository();
        if ($repository->findOffice($officeId) === null) {
            $this->notFound('Cargo não encontrado.', '/comissao/cargos');
        }

        $data = $this->officeData($request);
        $error = $this->validateOffice($data, $officeId);
        if ($error !== null) {
            $this->fail($error, '/comissao/cargos/' . $officeId . '/editar');
        }

        $repository->updateOffice($officeId, $data);
        $this->success('Cargo atualizado.', '/comissao/cargos');
    }

    public function destroyOffice(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        (new ComissaoRepository())->deleteOffice((int) $id);
        $this->success('Cargo removido.', '/comissao/cargos');
    }

    private function render(string $section, ?array $editing = null): void
    {
        $user = Auth::requireLogin();
        $repository = new ComissaoRepository();
        $records = match ($section) {
            'pessoas' => $repository->people(),
            'cargos' => $repository->offices(),
            default => $repository->commissions(),
        };

        View::render('modules/comissao/index', [
            'title' => match ($section) {
                'pessoas' => 'Pessoas da comissão',
                'cargos' => 'Cargos da igreja',
                default => 'Comissões de nomeações',
            },
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'section' => $section,
            'records' => $records,
            'editing' => $editing,
        ]);
    }

    private function personData(Request $request): array
    {
        return [
            'nome' => trim((string) $request->input('nome')),
            'cpf' => preg_replace('/\D+/', '', (string) $request->input('cpf')) ?? '',
        ];
    }

    private function validatePerson(array $data, ?int $ignoreId = null): ?string
    {
        if ($data['nome'] === '' || mb_strlen($data['nome']) > 160) {
            return 'Informe um nome com até 160 caracteres.';
        }
        if ($data['cpf'] !== '' && !$this->validCpf($data['cpf'])) {
            return 'Informe um CPF válido.';
        }
        if ($data['cpf'] !== '' && (new ComissaoRepository())->cpfExists($data['cpf'], $ignoreId)) {
            return 'Este CPF já está cadastrado.';
        }

        return null;
    }

    private function commissionData(Request $request): array
    {
        $situation = (string) $request->input('situacao', 'aguardando');

        return [
            'nome' => trim((string) $request->input('nome')),
            'data_inicio' => (string) $request->input('data_inicio'),
            'data_fim' => (string) $request->input('data_fim'),
            'situacao' => in_array($situation, self::SITUATIONS, true) ? $situation : 'aguardando',
        ];
    }

    private function validateCommission(array $data): ?string
    {
        if ($data['nome'] === '' || mb_strlen($data['nome']) > 160) {
            return 'Informe um nome com até 160 caracteres.';
        }
        if (!$this->validDate($data['data_inicio']) || !$this->validDate($data['data_fim'])) {
            return 'Informe as datas de início e fim.';
        }
        if ($data['data_fim'] < $data['data_inicio']) {
            return 'A data final não pode ser anterior à data inicial.';
        }

        return null;
    }

    private function officeData(Request $request): array
    {
        $status = (string) $request->input('status', 'ativo');

        return [
            'nome' => trim((string) $request->input('nome')),
            'status' => in_array($status, self::OFFICE_STATUSES, true) ? $status : 'ativo',
        ];
    }

    private function validateOffice(array $data, ?int $ignoreId = null): ?string
    {
        if ($data['nome'] === '' || mb_strlen($data['nome']) > 180) {
            return 'Informe o nome do cargo com até 180 caracteres.';
        }
        if ((new ComissaoRepository())->officeNameExists($data['nome'], $ignoreId)) {
            return 'Este cargo já está cadastrado.';
        }

        return null;
    }

    private function validCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) {
                $sum += (int) $cpf[$index] * (($digit + 1) - $index);
            }
            $check = (10 * $sum) % 11;
            if ($check === 10) {
                $check = 0;
            }
            if ($check !== (int) $cpf[$digit]) {
                return false;
            }
        }

        return true;
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function editableVote(int $commissionId, int $commissionOfficeId): array
    {
        $repository = new ComissaoRepository();
        $commission = $repository->findCommission($commissionId);
        if ($commission === null) {
            $this->notFound('Comissão não encontrada.', '/comissao/comissoes');
        }
        if ($commission['situacao'] !== 'iniciada') {
            $this->fail('Somente uma comissão iniciada pode alterar votações.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }

        $vote = $repository->findCommissionOffice($commissionId, $commissionOfficeId);
        if ($vote === null) {
            $this->notFound('Votação não encontrada nesta comissão.', '/comissao/comissoes/' . $commissionId . '/cargos');
        }

        return [$repository, $commission, $vote];
    }

    private function voteEntries(Request $request, int $commissionId, int $commissionOfficeId): array
    {
        $path = $this->votePath($commissionId, $commissionOfficeId);
        $votes = $request->input('votos', []);
        $roles = $request->input('papeis', []);
        if (!is_array($votes) || !is_array($roles)) {
            $this->fail('Os dados da votação são inválidos.', $path);
        }

        $entries = [];
        foreach ($votes as $participantId => $voteCount) {
            $id = (int) $participantId;
            $rawVoteCount = (string) $voteCount;
            $role = (string) ($roles[$participantId] ?? '');
            if ($id < 1 || !preg_match('/^\d+$/', $rawVoteCount) || (int) $rawVoteCount > 999999) {
                $this->fail('Informe uma quantidade de votos entre 0 e 999999.', $path);
            }
            if (!in_array($role, self::VOTE_ROLES, true)) {
                $this->fail('Selecione uma classificação válida para cada pessoa.', $path);
            }
            $entries[] = ['id' => $id, 'votos' => (int) $rawVoteCount, 'papel' => $role];
        }

        return $entries;
    }

    private function votePath(int $commissionId, int $commissionOfficeId): string
    {
        return '/comissao/comissoes/' . $commissionId . '/cargos?votacao=' . $commissionOfficeId;
    }

    private function membersPath(int $commissionId): string
    {
        return '/comissao/comissoes/' . $commissionId . '/cargos?membros=1';
    }

    private function fail(string $message, string $path): never
    {
        $_SESSION['flash_error'] = $message;
        redirect($path);
    }

    private function success(string $message, string $path): never
    {
        $_SESSION['flash_success'] = $message;
        redirect($path);
    }

    private function notFound(string $message, string $path): never
    {
        $_SESSION['flash_error'] = $message;
        redirect($path);
    }
}
