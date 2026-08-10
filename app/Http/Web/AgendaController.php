<?php

declare(strict_types=1);

namespace IasdCentral\Http\Web;

use IasdCentral\Core\Auth;
use IasdCentral\Core\Csrf;
use IasdCentral\Core\Request;
use IasdCentral\Core\View;
use IasdCentral\Repositories\AgendaRepository;
use IasdCentral\Repositories\MenuRepository;

final class AgendaController
{
    public function index(Request $request): void
    {
        $user = Auth::requireLogin();
        $month = $this->validMonth((string) $request->input('mes', date('Y-m')));
        $repository = new AgendaRepository();

        View::render('modules/agenda/index', [
            'title' => 'Agenda geral',
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'month' => $month,
            'calendar' => $this->calendar($month, $repository->eventsForMonth($month)),
            'summary' => $repository->summary($month),
            'upcoming' => $repository->upcoming(),
            'activities' => $repository->activities(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->form($request, null);
    }

    public function edit(Request $request, string $id): void
    {
        $event = (new AgendaRepository())->findEvent((int) $id);
        if (!$event) {
            $_SESSION['flash_error'] = 'Evento nao encontrado.';
            redirect('/agenda');
        }

        $this->form($request, $event);
    }

    public function store(Request $request): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $data = $this->eventData($request);
        $errors = $this->validateEvent($data);

        if ($errors !== []) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            redirect('/agenda/create');
        }

        (new AgendaRepository())->createEvent($data, (int) $user['id']);
        $_SESSION['flash_success'] = 'Evento cadastrado na agenda.';
        redirect('/agenda?mes=' . substr($data['data_inicio'], 0, 7));
    }

    public function update(Request $request, string $id): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $data = $this->eventData($request);
        $errors = $this->validateEvent($data);

        if ($errors !== []) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            redirect('/agenda/' . (int) $id . '/edit');
        }

        (new AgendaRepository())->updateEvent((int) $id, $data, (int) $user['id']);
        $_SESSION['flash_success'] = 'Evento atualizado.';
        redirect('/agenda?mes=' . substr($data['data_inicio'], 0, 7));
    }

    public function destroy(Request $request, string $id): void
    {
        Csrf::verify();
        Auth::requireLogin();
        (new AgendaRepository())->deleteEvent((int) $id);
        $_SESSION['flash_success'] = 'Evento removido da agenda.';
        redirect('/agenda');
    }

    public function storeActivity(Request $request): void
    {
        Csrf::verify();
        $user = Auth::requireLogin();
        $data = [
            'nome' => trim((string) $request->input('nome')),
            'descricao' => trim((string) $request->input('descricao')),
            'cor' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $request->input('cor')) ? (string) $request->input('cor') : '#7a1f38',
            'duracao_padrao_minutos' => (int) $request->input('duracao_padrao_minutos', 60),
            'exige_escala' => (int) $request->input('exige_escala', 0),
            'status' => in_array((string) $request->input('status'), ['Ativa', 'Inativa'], true) ? (string) $request->input('status') : 'Ativa',
        ];

        if ($data['nome'] === '') {
            $_SESSION['flash_error'] = 'Informe o nome da atividade.';
            redirect('/agenda');
        }

        (new AgendaRepository())->createActivity($data, (int) $user['id']);
        $_SESSION['flash_success'] = 'Atividade salva.';
        redirect('/agenda');
    }

    private function form(Request $request, ?array $event): void
    {
        $user = Auth::requireLogin();
        View::render('modules/agenda/form', [
            'title' => $event ? 'Editar evento' : 'Novo evento',
            'user' => $user,
            'menus' => (new MenuRepository())->activeTreeForUser((int) $user['id']),
            'event' => $event,
            'activities' => (new AgendaRepository())->activities(true),
            'selectedDate' => (string) $request->input('data', date('Y-m-d')),
        ]);
    }

    private function eventData(Request $request): array
    {
        return [
            'atividade_id' => (int) $request->input('atividade_id', 0),
            'titulo' => trim((string) $request->input('titulo')),
            'descricao' => trim((string) $request->input('descricao')),
            'data_inicio' => (string) $request->input('data_inicio'),
            'hora_inicio' => (string) $request->input('hora_inicio'),
            'data_fim' => (string) $request->input('data_fim'),
            'hora_fim' => (string) $request->input('hora_fim'),
            'dia_todo' => (int) $request->input('dia_todo', 0),
            'local' => trim((string) $request->input('local')),
            'responsavel' => trim((string) $request->input('responsavel')),
            'visibilidade' => in_array((string) $request->input('visibilidade'), ['Publica', 'Interna'], true) ? (string) $request->input('visibilidade') : 'Interna',
            'status' => in_array((string) $request->input('status'), ['Agendada', 'Confirmada', 'Cancelada', 'Concluida'], true) ? (string) $request->input('status') : 'Agendada',
            'observacoes' => trim((string) $request->input('observacoes')),
        ];
    }

    private function validateEvent(array $data): array
    {
        $errors = [];
        if ($data['titulo'] === '') {
            $errors[] = 'Informe o titulo do evento.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['data_inicio'])) {
            $errors[] = 'Informe uma data valida.';
        }
        if ($data['data_fim'] !== '' && $data['data_fim'] < $data['data_inicio']) {
            $errors[] = 'A data final nao pode ser anterior a data inicial.';
        }
        if (empty($data['dia_todo']) && $data['hora_inicio'] !== '' && $data['hora_fim'] !== '' && ($data['data_fim'] === '' || $data['data_fim'] === $data['data_inicio']) && $data['hora_fim'] < $data['hora_inicio']) {
            $errors[] = 'A hora final nao pode ser anterior a hora inicial.';
        }

        return $errors;
    }

    private function validMonth(string $month): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
    }

    private function calendar(string $month, array $events): array
    {
        $first = strtotime($month . '-01');
        $daysInMonth = (int) date('t', $first);
        $startWeekday = (int) date('w', $first);
        $eventsByDate = [];
        foreach ($events as $event) {
            $eventsByDate[$event['data_inicio']][] = $event;
        }

        $days = [];
        for ($i = 0; $i < $startWeekday; $i++) {
            $days[] = null;
        }
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $days[] = ['date' => $date, 'day' => $day, 'events' => $eventsByDate[$date] ?? []];
        }
        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        return $days;
    }
}
