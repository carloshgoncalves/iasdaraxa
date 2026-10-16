<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use DateTimeImmutable;
use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\ComissaoRepository;
use IasdCentral\Repositories\MenuRepository;

final class ComissaoController
{
    private const SITUATIONS = ['aguardando', 'iniciada', 'finalizada'];
    private const OFFICE_STATUSES = ['ativo', 'inativo'];

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
        if (!$this->validCpf($data['cpf'])) {
            return 'Informe um CPF válido.';
        }
        if ((new ComissaoRepository())->cpfExists($data['cpf'], $ignoreId)) {
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
