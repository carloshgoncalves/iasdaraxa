document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    document.querySelectorAll('[data-planning-mode-tabs]').forEach((tabList) => {
        const buttons = Array.from(tabList.querySelectorAll('[data-planning-view]'));
        const container = tabList.closest('[data-planning-tree]');
        const panels = Array.from(container.querySelectorAll('[data-planning-panel]'));
        const activate = (view) => {
            buttons.forEach((button) => {
                const active = button.dataset.planningView === view;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach((panel) => { panel.hidden = panel.dataset.planningPanel !== view; });
        };
        buttons.forEach((button) => button.addEventListener('click', () => activate(button.dataset.planningView)));
        activate(tabList.dataset.defaultView || 'planning');
    });

    document.querySelectorAll('[data-memorial-toggle]').forEach((toggle) => {
        const questionnaire = toggle.closest('form')?.querySelector('[data-memorial-questionnaire]');
        const update = () => { if (questionnaire) questionnaire.hidden = !toggle.checked; };
        toggle.addEventListener('change', update);
        update();
    });

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    document.querySelectorAll('.color-control input[type="color"]').forEach((input) => {
        const output = input.closest('.color-control')?.querySelector('code');
        input.addEventListener('input', () => {
            if (output) output.textContent = input.value;
            const variable = input.name === 'brand_color' ? '--brand' : '--sidebar-bg';
            document.body.style.setProperty(variable, input.value);
        });
    });
    document.querySelectorAll('.range-control input[type="range"]').forEach((input) => {
        const output = input.closest('.range-control')?.querySelector('output');
        input.addEventListener('input', () => {
            if (output) output.textContent = `${input.value} px`;
            document.body.style.setProperty('--sidebar-width', `${input.value}px`);
        });
    });

    const tooltip = document.createElement('div');
    tooltip.className = 'soft-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);

    const showTooltip = (target) => {
        tooltip.textContent = target.dataset.tooltip;
        tooltip.hidden = false;
        tooltip.classList.remove('visible');
        const targetRect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const left = Math.min(window.innerWidth - tooltipRect.width - 8, Math.max(8, targetRect.left + (targetRect.width - tooltipRect.width) / 2));
        const above = targetRect.top - tooltipRect.height - 8;
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${above >= 8 ? above : targetRect.bottom + 8}px`;
        window.requestAnimationFrame(() => tooltip.classList.add('visible'));
    };

    const hideTooltip = () => {
        tooltip.classList.remove('visible');
        window.setTimeout(() => {
            if (!tooltip.classList.contains('visible')) {
                tooltip.hidden = true;
            }
        }, 150);
    };

    document.querySelectorAll('[data-tooltip]').forEach((target) => {
        target.addEventListener('mouseenter', () => showTooltip(target));
        target.addEventListener('mouseleave', hideTooltip);
        target.addEventListener('focus', () => showTooltip(target));
        target.addEventListener('blur', hideTooltip);
    });
    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);

    document.querySelectorAll('[data-planning-dialog], [data-composition-dialog], [data-app-dialog]').forEach((dialog) => {
        const closeDialog = () => dialog.close();

        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', closeDialog);
        });

        dialog.querySelectorAll('[data-predecessor-filter]').forEach((filter) => {
            const options = Array.from(dialog.querySelectorAll('[data-predecessor-option]'));
            filter.addEventListener('input', () => {
                const query = filter.value.trim().toLocaleLowerCase('pt-BR');
                options.forEach((option) => {
                    option.hidden = query !== '' && !option.textContent.toLocaleLowerCase('pt-BR').includes(query);
                });
            });
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog();
            }
        });

        if (dialog.dataset.autoOpen === 'true') {
            dialog.showModal();
        }
    });

    document.querySelectorAll('[data-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.dialogOpen);
            if (dialog instanceof HTMLDialogElement) {
                button.closest('details')?.removeAttribute('open');
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('[data-sinapi-combobox]').forEach((combobox) => {
        const form = combobox.closest('[data-sinapi-form]');
        const search = combobox.querySelector('[data-sinapi-search]');
        const hiddenId = combobox.querySelector('[data-sinapi-id]');
        const options = combobox.querySelector('[data-sinapi-options]');
        const toggle = combobox.querySelector('[data-sinapi-toggle]');
        const unit = form?.querySelector('[data-sinapi-unit]');
        const unitPrice = form?.querySelector('[data-sinapi-unit-price]');
        const quantity = form?.querySelector('[data-sinapi-quantity]');
        const total = form?.querySelector('[data-sinapi-total]');
        const status = form?.querySelector('[data-resource-status]');
        let timer = null;
        let requestController = null;
        let activeIndex = -1;
        let currentPage = 1;

        const updateTotal = () => {
            if (!total) {
                return;
            }
            const value = Number.parseFloat(unitPrice?.value || '0') * Number.parseFloat(quantity?.value || '0');
            total.value = Number.isFinite(value) ? value.toFixed(2) : '0.00';
        };

        const closeOptions = () => {
            options.hidden = true;
            search.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        };

        const openOptions = () => {
            options.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        };

        const setActiveOption = (index) => {
            const buttons = Array.from(options.querySelectorAll('[role="option"]'));
            if (buttons.length === 0) {
                return;
            }
            activeIndex = Math.max(0, Math.min(index, buttons.length - 1));
            buttons.forEach((button, buttonIndex) => button.classList.toggle('active', buttonIndex === activeIndex));
            buttons[activeIndex].scrollIntoView({ block: 'nearest' });
        };

        const selectItem = (item) => {
            hiddenId.value = item.id;
            search.value = `${item.codigo} - ${item.descricao}`;
            search.setCustomValidity('');
            if (unit) {
                unit.value = item.unidade;
            }
            if (unitPrice) {
                unitPrice.value = item.preco_mg ?? '0';
            }
            updateTotal();
            closeOptions();
        };

        const renderOptions = (items, payload, append = false) => {
            if (!append) {
                options.replaceChildren();
            } else {
                options.querySelector('[data-combobox-footer]')?.remove();
            }
            if (items.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'combobox-empty';
                empty.textContent = 'Nenhum item encontrado.';
                options.appendChild(empty);
                openOptions();
                return;
            }

            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'option');
                const title = document.createElement('strong');
                title.textContent = `${item.codigo} - ${item.descricao}`;
                const detail = document.createElement('small');
                const price = item.preco_mg === null ? 'sem preco' : Number(item.preco_mg).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                const priceReference = item.competencia_preco ? ` · preco ${item.competencia_preco.slice(5, 7)}/${item.competencia_preco.slice(0, 4)}` : '';
                detail.textContent = `${item.unidade} · ${price}${priceReference}`;
                button.append(title, detail);
                button.addEventListener('click', () => selectItem(item));
                options.appendChild(button);
            });

            const footer = document.createElement('div');
            footer.className = 'combobox-footer';
            footer.dataset.comboboxFooter = '';
            const shown = options.querySelectorAll('[role="option"]').length;
            const counter = document.createElement('span');
            counter.textContent = `${shown.toLocaleString('pt-BR')} de ${Number(payload.total || shown).toLocaleString('pt-BR')} opcoes`;
            footer.appendChild(counter);
            if (payload.has_more) {
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'combobox-more';
                more.textContent = 'Carregar mais';
                more.addEventListener('click', () => loadOptions(currentPage + 1, true));
                footer.appendChild(more);
            }
            options.appendChild(footer);
            openOptions();
        };

        const loadOptions = async (page = 1, append = false) => {
            requestController?.abort();
            requestController = new AbortController();
            const endpoint = new URL(combobox.dataset.endpoint, window.location.origin);
            endpoint.searchParams.set('tipo', combobox.dataset.type);
            endpoint.searchParams.set('q', search.value.trim());
            endpoint.searchParams.set('page', String(page));
            if (!append) {
                options.innerHTML = '<p class="combobox-empty">Buscando...</p>';
            } else {
                options.querySelector('[data-combobox-footer]')?.replaceChildren('Buscando...');
            }
            openOptions();
            try {
                const response = await fetch(endpoint, { signal: requestController.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error('Falha ao consultar o SINAPI.');
                }
                const payload = await response.json();
                currentPage = page;
                renderOptions(payload.items || [], payload, append);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    options.innerHTML = '<p class="combobox-empty">Nao foi possivel carregar a lista.</p>';
                    openOptions();
                }
            }
        };

        search.addEventListener('input', () => {
            hiddenId.value = '';
            search.setCustomValidity('');
            clearTimeout(timer);
            currentPage = 1;
            timer = setTimeout(() => loadOptions(), 250);
        });
        search.addEventListener('focus', () => {
            if (options.childElementCount === 0) {
                loadOptions();
            } else {
                openOptions();
            }
        });
        search.addEventListener('keydown', (event) => {
            const buttons = Array.from(options.querySelectorAll('[role="option"]'));
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveOption(activeIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveOption(activeIndex <= 0 ? buttons.length - 1 : activeIndex - 1);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                buttons[activeIndex]?.click();
            } else if (event.key === 'Escape') {
                closeOptions();
            }
        });
        toggle.addEventListener('click', () => {
            if (options.hidden) {
                loadOptions();
                search.focus();
            } else {
                closeOptions();
            }
        });
        quantity?.addEventListener('input', updateTotal);
        form?.querySelector('[data-resource-type]')?.addEventListener('change', (event) => {
            combobox.dataset.type = event.target.value;
            hiddenId.value = '';
            search.value = '';
            options.replaceChildren();
            if (unit) unit.value = '';
            if (unitPrice) unitPrice.value = '';
            updateTotal();
            const statuses = {
                Material: ['Previsto', 'Solicitado', 'Comprado', 'Entregue', 'Cancelado'],
                Servico: ['Previsto', 'Cotando', 'Contratado', 'Em execucao', 'Medido', 'Concluido', 'Cancelado'],
                Equipamento: ['Previsto', 'Cotando', 'Contratado', 'Em uso', 'Devolvido', 'Cancelado'],
            };
            status?.replaceChildren(...(statuses[event.target.value] || ['Previsto']).map((value) => new Option(value, value)));
            currentPage = 1;
            loadOptions();
            search.focus();
        });
        form?.addEventListener('submit', (event) => {
            if (hiddenId.value === '' && !combobox.hasAttribute('data-sinapi-optional')) {
                event.preventDefault();
                search.setCustomValidity('Selecione uma opcao da lista SINAPI.');
                search.reportValidity();
                search.focus();
            }
        });
        document.addEventListener('click', (event) => {
            if (!combobox.contains(event.target)) {
                closeOptions();
            }
        });
        updateTotal();
    });

    document.querySelectorAll('[data-planning-tree]').forEach((tree) => {
        const rows = Array.from(tree.querySelectorAll('[data-planning-row]'));
        const rowsById = new Map(rows.map((row) => [row.dataset.itemId, row]));
        const collapsedIds = new Set();
        const levelSelect = tree.querySelector('[data-planning-level]');
        const tableWrap = tree.querySelector('[data-column-storage]');
        const columnToggles = Array.from(tree.querySelectorAll('[data-column-toggle]'));
        const defaultColumns = columnToggles.filter((toggle) => toggle.checked).map((toggle) => toggle.value);
        let visibleColumns = defaultColumns;
        try {
            const storedColumns = JSON.parse(localStorage.getItem(tableWrap?.dataset.columnStorage) || 'null');
            if (Array.isArray(storedColumns) && storedColumns.length > 0) visibleColumns = storedColumns;
        } catch (error) {
            visibleColumns = defaultColumns;
        }

        const updateColumns = () => {
            tree.querySelectorAll('[data-column]').forEach((cell) => {
                cell.hidden = !visibleColumns.includes(cell.dataset.column);
            });
            columnToggles.forEach((toggle) => { toggle.checked = visibleColumns.includes(toggle.value); });
            if (tableWrap?.dataset.columnStorage) localStorage.setItem(tableWrap.dataset.columnStorage, JSON.stringify(visibleColumns));
        };
        columnToggles.forEach((toggle) => {
            toggle.addEventListener('change', () => {
                const selected = columnToggles.filter((entry) => entry.checked).map((entry) => entry.value);
                if (selected.length === 0) {
                    toggle.checked = true;
                    return;
                }
                visibleColumns = selected;
                updateColumns();
            });
        });
        updateColumns();

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        let draggedRow = null;
        const clearDropTargets = () => tree.querySelectorAll('.drop-target').forEach((target) => target.classList.remove('drop-target'));
        const moveItem = async (row, parentId) => {
            const body = new URLSearchParams({_csrf: csrfToken, parent_id: parentId || '0'});
            const response = await fetch(row.dataset.moveEndpoint, {method: 'POST', body, headers: {Accept: 'application/json'}});
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Nao foi possivel mover o item.');
            window.location.reload();
        };
        rows.forEach((row) => {
            const handle = row.querySelector('[data-drag-handle]');
            handle?.addEventListener('dragstart', (event) => {
                draggedRow = row;
                row.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', row.dataset.itemId);
            });
            handle?.addEventListener('dragend', () => {
                row.classList.remove('dragging');
                draggedRow = null;
                clearDropTargets();
            });
            row.addEventListener('dragover', (event) => {
                if (!draggedRow || draggedRow === row) return;
                event.preventDefault();
                clearDropTargets();
                row.classList.add('drop-target');
            });
            row.addEventListener('drop', async (event) => {
                event.preventDefault();
                if (!draggedRow || draggedRow === row) return;
                clearDropTargets();
                try { await moveItem(draggedRow, row.dataset.itemId); } catch (error) { window.alert(error.message); }
            });
        });
        const rootDrop = tree.querySelector('[data-root-drop]');
        rootDrop?.addEventListener('dragover', (event) => {
            if (!draggedRow) return;
            event.preventDefault();
            clearDropTargets();
            rootDrop.classList.add('drop-target');
        });
        rootDrop?.addEventListener('dragleave', () => rootDrop.classList.remove('drop-target'));
        rootDrop?.addEventListener('drop', async (event) => {
            event.preventDefault();
            rootDrop.classList.remove('drop-target');
            if (!draggedRow) return;
            try { await moveItem(draggedRow, 0); } catch (error) { window.alert(error.message); }
        });

        const hasCollapsedAncestor = (row) => {
            let parentId = row.dataset.parentId;
            while (parentId && parentId !== '0') {
                if (collapsedIds.has(parentId)) {
                    return true;
                }
                parentId = rowsById.get(parentId)?.dataset.parentId || '0';
            }
            return false;
        };

        const updateTree = () => {
            const maxLevel = Number.parseInt(levelSelect?.value || '0', 10);
            rows.forEach((row) => {
                const levelHidden = maxLevel > 0 && Number.parseInt(row.dataset.depth, 10) + 1 > maxLevel;
                row.hidden = levelHidden || hasCollapsedAncestor(row);
                const toggleButton = row.querySelector('[data-tree-toggle]');
                if (toggleButton) {
                    const collapsed = collapsedIds.has(row.dataset.itemId);
                    toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    toggleButton.textContent = collapsed ? '▸' : '▾';
                    const action = collapsed ? 'Expandir' : 'Recolher';
                    toggleButton.title = `${action} ${row.querySelector('.wbs-control > span:last-child')?.textContent || ''}`;
                    toggleButton.setAttribute('aria-label', `${action} subitens`);
                }
            });
        };

        rows.forEach((row) => {
            row.querySelector('[data-tree-toggle]')?.addEventListener('click', () => {
                const itemId = row.dataset.itemId;
                if (collapsedIds.has(itemId)) {
                    collapsedIds.delete(itemId);
                } else {
                    collapsedIds.add(itemId);
                }
                updateTree();
            });
        });

        levelSelect?.addEventListener('change', updateTree);
        tree.querySelector('[data-tree-collapse-all]')?.addEventListener('click', () => {
            rows.forEach((row) => {
                if (row.querySelector('[data-tree-toggle]')) {
                    collapsedIds.add(row.dataset.itemId);
                }
            });
            updateTree();
        });
        tree.querySelector('[data-tree-expand-all]')?.addEventListener('click', () => {
            collapsedIds.clear();
            if (levelSelect) {
                levelSelect.value = '';
            }
            updateTree();
        });
        updateTree();
    });

    document.querySelectorAll('[data-tabs]').forEach((tabList) => {
        const tabs = Array.from(tabList.querySelectorAll('[role="tab"]'));
        const panels = tabs
            .map((tab) => document.getElementById(tab.getAttribute('aria-controls')))
            .filter(Boolean);

        const activateTab = (tab, updateHistory = false) => {
            const panelId = tab.getAttribute('aria-controls');

            tabs.forEach((item) => {
                const active = item === tab;
                item.setAttribute('aria-selected', active ? 'true' : 'false');
                item.setAttribute('tabindex', active ? '0' : '-1');
            });

            panels.forEach((panel) => {
                panel.hidden = panel.id !== panelId;
            });

            if (updateHistory && window.location.hash !== `#${panelId}`) {
                window.history.pushState(null, '', `#${panelId}`);
            }
        };

        const activateFromHash = () => {
            const panelId = window.location.hash.slice(1);
            const tab = tabs.find((item) => item.getAttribute('aria-controls') === panelId) || tabs[0];
            activateTab(tab);
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', (event) => {
                event.preventDefault();
                activateTab(tab, true);
            });

            tab.addEventListener('keydown', (event) => {
                let nextIndex = null;

                if (event.key === 'ArrowRight') {
                    nextIndex = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = tabs.length - 1;
                }

                if (nextIndex !== null) {
                    event.preventDefault();
                    tabs[nextIndex].focus();
                    activateTab(tabs[nextIndex], true);
                }
            });
        });

        window.addEventListener('hashchange', activateFromHash);
        window.addEventListener('popstate', activateFromHash);
        activateFromHash();
    });
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]');
    if (!button) {
        return;
    }

    if (!window.confirm(button.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

document.querySelector('[data-theme-selector]')?.addEventListener('change', (event) => {
    document.body.classList.remove('theme-light', 'theme-dark');
    document.body.classList.add(`theme-${event.target.value}`);
});

const migratorButton = document.getElementById('updateFtp');
const migratorOutput = document.getElementById('migratorOutput');
const migratorCsrf = document.getElementById('migratorCsrf');

if (migratorButton && migratorOutput && migratorCsrf) {
    migratorButton.addEventListener('click', async () => {
        migratorButton.disabled = true;
        migratorOutput.textContent = 'Iniciando atualizacao FTP...\n';

        const data = new FormData();
        data.append('_csrf', migratorCsrf.value);

        try {
            const response = await fetch(migratorButton.dataset.endpoint, { method: 'POST', body: data });
            if (!response.body) {
                throw new Error('O servidor nao disponibilizou o fluxo de resposta.');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                migratorOutput.textContent += decoder.decode(value, { stream: true });
                migratorOutput.scrollTop = migratorOutput.scrollHeight;
            }
        } catch (error) {
            migratorOutput.textContent += `\n[ERROR] ${error.message}`;
        } finally {
            migratorButton.disabled = false;
        }
    });
}
