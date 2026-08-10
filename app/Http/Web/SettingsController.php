<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\MenuRepository;
use IasdCentral\Repositories\DirectoryRepository;
use IasdCentral\Repositories\SinapiRepository;
use IasdCentral\Repositories\SettingsRepository;
use IasdCentral\Repositories\UserRepository;
use IasdCentral\Support\SinapiUpdateService;

final class SettingsController
{
    public function index(Request $request): void
    {
        $user = $this->requireAdmin();
        $users = new UserRepository();
        $editingId = (int) $request->input('editar_usuario', 0);
        $directory = new DirectoryRepository();
        View::render('settings/index', [
            'title' => 'Configuracoes',
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'settings' => (new SettingsRepository())->all(),
            'users' => $users->allWithRoles(),
            'roles' => $users->roles(),
            'editingUser' => $editingId > 0 ? $users->find($editingId) : null,
            'clients' => $directory->clients(),
            'suppliers' => $directory->suppliers(),
            'editingClient' => $directory->findClient((int) $request->input('editar_cliente', 0)),
            'editingSupplier' => $directory->findSupplier((int) $request->input('editar_fornecedor', 0)),
            'sinapiReference' => (new SinapiRepository())->referenceSummary(),
        ]);
    }

    public function updateAppearance(Request $request): void
    {
        Csrf::verify();
        $user = $this->requireAdmin();
        $font = $this->option($request, 'font_family', ['Arial', 'Inter', 'Georgia', 'System'], 'Arial');
        $density = $this->option($request, 'layout_density', ['comfortable', 'compact'], 'comfortable');
        $language = $this->option($request, 'language', ['pt-BR', 'en', 'es'], 'pt-BR');
        $brand = $this->color((string) $request->input('brand_color'), '#7a1f38');
        $sidebar = $this->color((string) $request->input('sidebar_color'), '#251b20');
        $width = min(320, max(220, (int) $request->input('sidebar_width', 250)));
        $theme = $this->option($request, 'theme_mode', ['light', 'dark'], 'light');
        (new SettingsRepository())->update([
            'font_family' => $font, 'layout_density' => $density, 'language' => $language,
            'brand_color' => $brand, 'sidebar_color' => $sidebar, 'sidebar_width' => (string) $width,
            'theme_mode' => $theme,
        ], (int) $user['id']);
        $_SESSION['flash_success'] = 'Aparencia do sistema atualizada.';
        redirect('/configuracoes#aparencia');
    }

    public function storeUser(Request $request): void
    {
        Csrf::verify();
        $this->requireAdmin();
        $data = $this->userData($request);
        if ($data['name'] === '' || $data['username'] === '' || $data['password'] === '') {
            $_SESSION['flash_error'] = 'Informe nome, usuario e senha.';
            redirect('/configuracoes#usuarios');
        }
        try {
            (new UserRepository())->insert($data);
            $_SESSION['flash_success'] = 'Usuario criado.';
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Nao foi possivel criar. Verifique se o usuario ja existe.';
        }
        redirect('/configuracoes#usuarios');
    }

    public function updateUser(Request $request, string $id): void
    {
        Csrf::verify();
        $current = $this->requireAdmin();
        $data = $this->userData($request);
        if ((int) $id === (int) $current['id']) $data['status'] = 'Ativo';
        if ($data['name'] === '' || $data['username'] === '') {
            $_SESSION['flash_error'] = 'Informe nome e usuario.';
            redirect('/configuracoes?editar_usuario=' . (int) $id . '#usuarios');
        }
        try {
            (new UserRepository())->update((int) $id, $data);
            $_SESSION['flash_success'] = 'Usuario atualizado.';
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Nao foi possivel atualizar o usuario.';
        }
        redirect('/configuracoes#usuarios');
    }

    public function storeClient(Request $request): void
    {
        $this->saveDirectoryEntry($request, null, 'client');
    }

    public function updateClient(Request $request, string $id): void
    {
        $this->saveDirectoryEntry($request, (int) $id, 'client');
    }

    public function storeSupplier(Request $request): void
    {
        $this->saveDirectoryEntry($request, null, 'supplier');
    }

    public function updateSupplier(Request $request, string $id): void
    {
        $this->saveDirectoryEntry($request, (int) $id, 'supplier');
    }

    public function checkSinapi(Request $request): void
    {
        Csrf::verify();
        $this->requireAdmin();
        $result = (new SinapiUpdateService())->run(false);
        $_SESSION[$result['status'] === 'error' ? 'flash_error' : 'flash_success'] = $result['message'];
        redirect('/configuracoes#banco-dados');
    }

    private function saveDirectoryEntry(Request $request, ?int $id, string $type): void
    {
        Csrf::verify();
        $this->requireAdmin();
        $name = trim((string) $request->input('nome'));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Informe o nome do cadastro.';
            redirect('/configuracoes#banco-dados');
        }
        $data = [
            'nome' => $name,
            'documento' => trim((string) $request->input('documento')) ?: null,
            'contato' => trim((string) $request->input('contato')) ?: null,
            'telefone' => trim((string) $request->input('telefone')) ?: null,
            'observacoes' => trim((string) $request->input('observacoes')) ?: null,
            'status' => $this->option($request, 'status', ['Ativo', 'Inativo'], 'Ativo'),
        ];
        $repository = new DirectoryRepository();
        if ($type === 'client') {
            $data['email'] = trim((string) $request->input('email')) ?: null;
            $repository->saveClient($id, $data);
        } else {
            $repository->saveSupplier($id, $data);
        }
        $_SESSION['flash_success'] = ($type === 'client' ? 'Cliente' : 'Fornecedor') . ' salvo.';
        redirect('/configuracoes#banco-dados');
    }

    private function requireAdmin(): array
    {
        $user = Auth::requireLogin();
        if (!(new UserRepository())->isAdmin((int) $user['id'])) {
            http_response_code(403);
            exit('Acesso restrito a administradores.');
        }
        return $user;
    }

    private function userData(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name')),
            'username' => trim((string) $request->input('username')),
            'password' => (string) $request->input('password'),
            'status' => $this->option($request, 'status', ['Ativo', 'Inativo'], 'Ativo'),
            'role_id' => (int) $request->input('role_id', 0),
        ];
    }

    private function option(Request $request, string $key, array $allowed, string $default): string
    {
        $value = (string) $request->input($key, $default);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function color(string $value, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }
}
