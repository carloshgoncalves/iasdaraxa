<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\CommissionMemberAuth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\ComissaoRepository;

final class CommissionMemberController
{
    public function index(Request $request): void
    {
        $member = (new CommissionMemberAuth())->member();
        if ($member === null) {
            View::render('modules/comissao/member-login', [
                'title' => 'Acesso da comissão',
            ], 'layouts/member');
            return;
        }

        View::render('modules/comissao/member-home', [
            'title' => 'Comissão de nomeações',
            'member' => $member,
            'stages' => (new ComissaoRepository())->commissionMemberStages((int) $member['comissao_id']),
        ], 'layouts/member');
    }

    public function login(Request $request): void
    {
        Csrf::verify();
        $code = trim((string) $request->input('codigo'));
        if (!(new CommissionMemberAuth())->attempt($code)) {
            $_SESSION['flash_error'] = 'Código de membro inválido. Confira os seis dígitos e tente novamente.';
            redirect('/comissao/membro');
        }

        redirect('/comissao/membro');
    }

    public function logout(Request $request): void
    {
        Csrf::verify();
        (new CommissionMemberAuth())->logout();
        redirect('/comissao/membro');
    }

    public function indications(Request $request, string $voteId): void
    {
        $this->renderStage((int) $voteId, 'indicacao', 'indicar');
    }

    public function voting(Request $request, string $voteId): void
    {
        $this->renderStage((int) $voteId, 'votando', 'votar');
    }

    public function addIndication(Request $request, string $voteId): void
    {
        Csrf::verify();
        $id = (int) $voteId;
        [$member, $repository] = $this->memberStage($id, 'indicacao');
        $personId = (int) $request->input('pessoa_id', 0);

        try {
            if ($personId < 1) {
                throw new \InvalidArgumentException('Selecione uma pessoa cadastrada.');
            }
            if (!$repository->addMemberIndication($id, (int) $member['id'], $personId)) {
                throw new \InvalidArgumentException('Esta pessoa já foi indicada por você.');
            }
            $_SESSION['flash_success'] = 'Nome adicionado às suas indicações.';
        } catch (\InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        }

        redirect($this->indicationPath($id));
    }

    public function removeIndication(Request $request, string $voteId, string $indicationId): void
    {
        Csrf::verify();
        $id = (int) $voteId;
        [$member, $repository] = $this->memberStage($id, 'indicacao');

        try {
            $repository->removeMemberIndication($id, (int) $member['id'], (int) $indicationId);
            $_SESSION['flash_success'] = 'Nome retirado das suas indicações.';
        } catch (\InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        }

        redirect($this->indicationPath($id));
    }

    public function finishIndications(Request $request, string $voteId): void
    {
        Csrf::verify();
        $id = (int) $voteId;
        [$member, $repository] = $this->memberStage($id, 'indicacao');

        try {
            $repository->finishMemberIndications($id, (int) $member['id']);
            $_SESSION['flash_success'] = 'Suas indicações foram encerradas.';
        } catch (\InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        }

        redirect($this->indicationPath($id));
    }

    public function confirmVote(Request $request, string $voteId): void
    {
        Csrf::verify();
        $id = (int) $voteId;
        [$member, $repository] = $this->memberStage($id, 'votando');
        $selected = $request->input('candidatos', []);

        try {
            if (!is_array($selected)) {
                throw new \InvalidArgumentException('Selecione os candidatos para confirmar seu voto.');
            }
            $repository->confirmMemberVote($id, (int) $member['id'], $selected);
            $_SESSION['flash_success'] = 'Voto confirmado.';
        } catch (\InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        }

        redirect('/comissao/membro/votacoes/' . $id);
    }

    private function renderStage(int $voteId, string $expectedStatus, string $mode): void
    {
        [$member, $repository, $stage] = $this->memberStage($voteId, $expectedStatus);
        $indications = [];
        $availablePeople = [];
        $indicationsFinished = false;
        $votingCandidates = [];
        $voteReceipt = null;
        if ($mode === 'indicar') {
            $indications = $repository->memberIndications($voteId, (int) $member['id']);
            $indicationsFinished = $repository->hasMemberFinishedIndications($voteId, (int) $member['id']);
            if (!$indicationsFinished && count($indications) < (int) $stage['limite_indicacoes']) {
                $availablePeople = $repository->availablePeopleForIndication($voteId, (int) $member['id']);
            }
        } else {
            $votingCandidates = $repository->memberVotingCandidates($voteId);
            $voteReceipt = $repository->memberVoteReceipt($voteId, (int) $member['id']);
        }

        View::render('modules/comissao/member-stage', [
            'title' => $mode === 'indicar' ? 'Indicar nome' : 'Registrar voto',
            'member' => $member,
            'stage' => $stage,
            'mode' => $mode,
            'indications' => $indications,
            'availablePeople' => $availablePeople,
            'indicationsFinished' => $indicationsFinished,
            'votingCandidates' => $votingCandidates,
            'voteReceipt' => $voteReceipt,
        ], 'layouts/member');
    }

    private function memberStage(int $voteId, string $expectedStatus): array
    {
        $member = (new CommissionMemberAuth())->member();
        if ($member === null) {
            redirect('/comissao/membro');
        }

        $repository = new ComissaoRepository();
        $stage = $repository->findCommissionMemberStage((int) $member['comissao_id'], $voteId);
        if ($stage === null || $stage['votacao_status'] !== 'aberta' || $stage['etapa_status'] !== $expectedStatus) {
            $_SESSION['flash_error'] = 'Esta etapa não está disponível para o cargo selecionado.';
            redirect('/comissao/membro');
        }

        return [$member, $repository, $stage];
    }

    private function indicationPath(int $voteId): string
    {
        return '/comissao/membro/indicacoes/' . $voteId;
    }
}
