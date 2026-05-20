(function () {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var table = document.getElementById('serversTable');
    var search = document.getElementById('tableSearch');
    var filter = document.getElementById('statusFilter');
    var modal = document.getElementById('serverModal');
    var detailsModal = document.getElementById('detailsModal');
    var form = document.getElementById('serverForm');
    var serverType = document.getElementById('serverType');
    var portPreset = document.getElementById('serverPortPreset');
    var servicePortFields = document.getElementById('servicePortFields');
    var customPortWrap = document.getElementById('serverCustomPortWrap');
    var serverPort = document.getElementById('serverPort');
    var settingsForm = document.getElementById('settingsForm');
    var cronSettingsForm = document.getElementById('cronSettingsForm');
    var rotateCronToken = document.getElementById('rotateCronToken');
    var cronToken = document.getElementById('cronToken');
    var cronUrl = document.getElementById('cronUrl');
    var toast = document.getElementById('toast');
    var sortState = { index: null, dir: 1 };

    function showToast(message) {
        if (!toast) {
            window.alert(message);
            return;
        }
        toast.textContent = message;
        toast.hidden = false;
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(function () {
            toast.hidden = true;
        }, 3200);
    }

    function rows() {
        if (!table || !table.tBodies.length) {
            return [];
        }
        return Array.prototype.slice.call(table.tBodies[0].rows);
    }

    function applyFilters() {
        var query = search ? search.value.toLowerCase() : '';
        var wantedStatus = filter ? filter.value : '';
        rows().forEach(function (row) {
            var matchesQuery = row.textContent.toLowerCase().indexOf(query) !== -1;
            var matchesStatus = !wantedStatus || row.getAttribute('data-status') === wantedStatus;
            row.hidden = !(matchesQuery && matchesStatus);
        });
    }

    function cellValue(row, index) {
        var cell = row.cells[index];
        if (!cell) {
            return '';
        }
        return cell.getAttribute('data-value') || cell.textContent.trim().toLowerCase();
    }

    function applySort() {
        if (!table || sortState.index === null) {
            return;
        }
        var body = table.tBodies[0];
        rows().sort(function (a, b) {
            var av = cellValue(a, sortState.index);
            var bv = cellValue(b, sortState.index);
            var an = Number(av);
            var bn = Number(bv);
            if (!Number.isNaN(an) && !Number.isNaN(bn)) {
                return (an - bn) * sortState.dir;
            }
            return av.localeCompare(bv) * sortState.dir;
        }).forEach(function (row) {
            body.appendChild(row);
        });
    }

    function refreshTableState() {
        applyFilters();
        applySort();
    }

    function setupActivityTable() {
        var activityTable = document.getElementById('activityTable');
        var activitySearch = document.getElementById('activitySearch');
        var activityStatusFilter = document.getElementById('activityStatusFilter');
        var activityTypeFilter = document.getElementById('activityTypeFilter');
        var visibleCount = document.getElementById('activityVisibleCount');
        var activitySort = { index: null, dir: 1 };

        if (!activityTable || !activityTable.tBodies.length) {
            return;
        }

        function activityRows() {
            return Array.prototype.slice.call(activityTable.tBodies[0].rows);
        }

        function activityCellValue(row, index) {
            var cell = row.cells[index];
            if (!cell) {
                return '';
            }
            return cell.getAttribute('data-value') || cell.textContent.trim().toLowerCase();
        }

        function applyActivityFilters() {
            var query = activitySearch ? activitySearch.value.toLowerCase() : '';
            var wantedStatus = activityStatusFilter ? activityStatusFilter.value : '';
            var wantedType = activityTypeFilter ? activityTypeFilter.value : '';
            var count = 0;

            activityRows().forEach(function (row) {
                var haystack = row.getAttribute('data-search') || row.textContent.toLowerCase();
                var matchesQuery = haystack.indexOf(query) !== -1;
                var matchesStatus = !wantedStatus || row.getAttribute('data-status') === wantedStatus;
                var matchesType = !wantedType || row.getAttribute('data-type') === wantedType;
                var visible = matchesQuery && matchesStatus && matchesType;
                row.hidden = !visible;
                if (visible && row.cells.length > 1) {
                    count += 1;
                }
            });

            if (visibleCount) {
                visibleCount.textContent = String(count);
            }
        }

        function applyActivitySort() {
            if (activitySort.index === null) {
                return;
            }
            var body = activityTable.tBodies[0];
            activityRows().sort(function (a, b) {
                var av = activityCellValue(a, activitySort.index);
                var bv = activityCellValue(b, activitySort.index);
                var an = Number(av);
                var bn = Number(bv);
                if (!Number.isNaN(an) && !Number.isNaN(bn)) {
                    return (an - bn) * activitySort.dir;
                }
                return av.localeCompare(bv) * activitySort.dir;
            }).forEach(function (row) {
                body.appendChild(row);
            });
        }

        Array.prototype.slice.call(activityTable.querySelectorAll('th[data-sort]')).forEach(function (header, index) {
            header.addEventListener('click', function () {
                activitySort.dir = activitySort.index === index ? activitySort.dir * -1 : 1;
                activitySort.index = index;
                applyActivitySort();
                applyActivityFilters();
            });
        });

        if (activitySearch) {
            activitySearch.addEventListener('input', applyActivityFilters);
        }
        if (activityStatusFilter) {
            activityStatusFilter.addEventListener('change', applyActivityFilters);
        }
        if (activityTypeFilter) {
            activityTypeFilter.addEventListener('change', applyActivityFilters);
        }

        applyActivityFilters();
    }

    function rowFromHtml(html) {
        var template = document.createElement('template');
        template.innerHTML = String(html || '').trim();
        return template.content.firstElementChild;
    }

    function post(action, formData) {
        formData.append('_csrf', csrf);
        return fetch('index.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            body: formData
        }).then(function (response) {
            return response.text().then(function (text) {
                var payload;
                try {
                    payload = JSON.parse(text);
                } catch (error) {
                    throw new Error('Server lieferte kein JSON: ' + text.replace(/<[^>]+>/g, ' ').trim().slice(0, 220));
                }
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'Aktion fehlgeschlagen.');
                }
                return payload;
            });
        });
    }

    function replaceOrAppendRow(html) {
        if (!table) {
            return;
        }
        var next = rowFromHtml(html);
        if (!next) {
            return;
        }
        var current = table.querySelector('tbody tr[data-server-id="' + next.getAttribute('data-server-id') + '"]');
        if (current) {
            current.parentNode.replaceChild(next, current);
        } else {
            table.tBodies[0].appendChild(next);
        }
        refreshTableState();
    }

    function fill(id, value) {
        var element = document.getElementById(id);
        if (element) {
            element.value = value || '';
        }
    }

    function check(id, value) {
        var element = document.getElementById(id);
        if (element) {
            element.checked = value === true || value === '1' || value === 1;
        }
    }

    function closestElement(element, selector) {
        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) {
                return element;
            }
            element = element.parentElement;
        }
        return null;
    }

    function openModal(row) {
        if (!modal || !form) {
            return false;
        }
        form.reset();
        document.getElementById('modalTitle').textContent = row ? 'Check bearbeiten' : 'Neuer Check';
        fill('serverId', row ? row.getAttribute('data-server-id') : '');
        fill('serverName', row ? row.getAttribute('data-name') : '');
        fill('serverUrl', row ? row.getAttribute('data-url') : '');
        fill('serverType', row ? row.getAttribute('data-type') : 'website');
        setPortValue(row ? row.getAttribute('data-port') : '');
        fill('serverMethod', row ? row.getAttribute('data-method') : 'GET');
        fill('serverExpectedStatus', row ? row.getAttribute('data-expected-status') : '200-399');
        fill('serverExpectedText', row ? row.getAttribute('data-expected-text') : '');
        var timeoutField = document.getElementById('serverTimeout');
        fill('serverTimeout', row ? row.getAttribute('data-timeout') : (timeoutField ? timeoutField.getAttribute('data-default') : '10'));
        fill('serverCheckInterval', row ? row.getAttribute('data-check-interval') : '5');
        check('serverEnabled', !row || row.getAttribute('data-enabled') === '1');
        check('serverPublicVisible', !row || row.getAttribute('data-public-visible') === '1');
        check('serverNotifyEnabled', row && row.getAttribute('data-notify-enabled') === '1');
        fill('serverNotifyEmail', row ? row.getAttribute('data-notify-email') : '');
        check('serverNotifyOnDown', !row || row.getAttribute('data-notify-on-down') === '1');
        check('serverNotifyOnRecovery', !row || row.getAttribute('data-notify-on-recovery') === '1');
        modal.classList.add('is-open');
        modal.style.display = 'grid';
        modal.setAttribute('aria-hidden', 'false');
        updateServicePortVisibility();
        return false;
    }

    function closeModal() {
        if (!modal) {
            return false;
        }
        modal.classList.remove('is-open');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        return false;
    }

    window.serverMonitorOpenModal = openModal;
    window.serverMonitorCloseModal = closeModal;

    if (modal) {
        closeModal();
    }

    if (table) {
        table.addEventListener('click', function (event) {
            var button = closestElement(event.target, 'button[data-action]');
            if (!button) {
                return;
            }
            var row = closestElement(button, 'tr');
            var id = row ? row.getAttribute('data-server-id') : '';
            if (!id) {
                return;
            }

            if (button.getAttribute('data-action') === 'edit') {
                openModal(row);
                return;
            }

            if (button.getAttribute('data-action') === 'details') {
                var detailData = new FormData();
                detailData.append('id', id);
                post('server.details', detailData).then(function (payload) {
                    openDetails(payload.details);
                }).catch(function (error) {
                    showToast(error.message);
                });
                return;
            }

            if (button.getAttribute('data-action') === 'delete') {
                if (!window.confirm('Diesen Eintrag wirklich loeschen?')) {
                    return;
                }
                var deleteData = new FormData();
                deleteData.append('id', id);
                post('server.delete', deleteData).then(function () {
                    row.parentNode.removeChild(row);
                    showToast('Eintrag geloescht.');
                }).catch(function (error) {
                    showToast(error.message);
                });
                return;
            }

            var data = new FormData();
            data.append('id', id);
            button.disabled = true;
            post(button.getAttribute('data-action') === 'check' ? 'server.check' : 'server.toggle', data)
                .then(function (payload) {
                    replaceOrAppendRow(payload.row);
                    showToast(payload.message || 'Aktualisiert.');
                    button.disabled = false;
                })
                .catch(function (error) {
                    showToast(error.message);
                    button.disabled = false;
                });
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            syncPortBeforeSubmit();
            post('server.save', new FormData(form)).then(function (payload) {
                replaceOrAppendRow(payload.row);
                closeModal();
                showToast('Eintrag gespeichert.');
            }).catch(function (error) {
                showToast(error.message);
            });
        });
    }

    function setPortValue(value) {
        var known = ['80', '443', '21', '25', '465', '110', '995', '143', '993', '22', '389', '3306', '115', '43', '53', '3389'];
        value = value || '53';
        if (portPreset) {
            portPreset.value = known.indexOf(String(value)) !== -1 ? String(value) : 'custom';
        }
        if (serverPort) {
            serverPort.value = String(value);
        }
        updateCustomPortVisibility();
    }

    function syncPortBeforeSubmit() {
        if (!serverType || serverType.value !== 'tcp') {
            if (serverPort) {
                serverPort.value = '';
            }
            return;
        }
        if (portPreset && serverPort && portPreset.value !== 'custom') {
            serverPort.value = portPreset.value;
        }
    }

    function updateServicePortVisibility() {
        var show = serverType && serverType.value === 'tcp';
        if (servicePortFields) {
            var labels = servicePortFields.querySelectorAll('label');
            Array.prototype.slice.call(labels).forEach(function (label) {
                label.style.display = show ? 'grid' : 'none';
            });
        }
        updateCustomPortVisibility();
    }

    function updateCustomPortVisibility() {
        var show = serverType && serverType.value === 'tcp' && portPreset && portPreset.value === 'custom';
        if (customPortWrap) {
            customPortWrap.style.display = show ? 'grid' : 'none';
        }
        if (portPreset && serverPort && portPreset.value !== 'custom') {
            serverPort.value = portPreset.value;
        }
    }

    if (serverType) {
        serverType.addEventListener('change', updateServicePortVisibility);
    }
    if (portPreset) {
        portPreset.addEventListener('change', updateCustomPortVisibility);
    }
    updateServicePortVisibility();

    function submitSettings(targetForm) {
        post('settings.save', new FormData(targetForm)).then(function (payload) {
            showToast(payload.message || 'Einstellungen gespeichert.');
        }).catch(function (error) {
            showToast(error.message);
        });
    }

    function setupSettingsTabs() {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-settings-tab]'));
        var panels = Array.prototype.slice.call(document.querySelectorAll('[data-settings-panel]'));
        if (!tabs.length || !panels.length) {
            return;
        }

        function activate(name) {
            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-settings-tab') === name;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var active = panel.getAttribute('data-settings-panel') === name;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
            try {
                window.localStorage.setItem('serverMonitorSettingsTab', name);
            } catch (error) {
                return;
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-settings-tab'));
            });
        });

        var saved = '';
        try {
            saved = window.localStorage.getItem('serverMonitorSettingsTab') || '';
        } catch (error) {
            saved = '';
        }
        if (!tabs.some(function (tab) { return tab.getAttribute('data-settings-tab') === saved; })) {
            saved = 'mail';
        }
        activate(saved || 'mail');
    }

    if (settingsForm) {
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitSettings(settingsForm);
        });
    }

    if (cronSettingsForm) {
        cronSettingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitSettings(cronSettingsForm);
        });
    }

    if (rotateCronToken) {
        rotateCronToken.addEventListener('click', function () {
            post('cron.token.rotate', new FormData()).then(function (payload) {
                if (cronToken) {
                    cronToken.value = payload.token || '';
                }
                if (cronUrl && payload.token) {
                    cronUrl.value = cronUrl.value.replace(/token=[^&]*/, 'token=' + encodeURIComponent(payload.token));
                }
                showToast(payload.message || 'Cron-Token neu erzeugt.');
            }).catch(function (error) {
                showToast(error.message);
            });
        });
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-public-page-form]')).forEach(function (pageForm) {
        pageForm.addEventListener('submit', function (event) {
            event.preventDefault();
            post('public_page.save', new FormData(pageForm)).then(function (payload) {
                showToast(payload.message || 'Public Page gespeichert.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 650);
            }).catch(function (error) {
                showToast(error.message);
            });
        });
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-delete-public-page]')).forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            if (!window.confirm('Diese Public Page wirklich loeschen?')) {
                return;
            }
            var data = new FormData();
            data.append('id', button.getAttribute('data-delete-public-page'));
            post('public_page.delete', data).then(function (payload) {
                showToast(payload.message || 'Public Page geloescht.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 650);
            }).catch(function (error) {
                showToast(error.message);
            });
        });
    });

    Array.prototype.slice.call(document.querySelectorAll('.public-page-card-head a')).forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    setupSettingsTabs();

    Array.prototype.slice.call(document.querySelectorAll('[data-open-modal]')).forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            openModal(null);
        });
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-close-modal]')).forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            closeModal();
        });
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    function closeDetails() {
        if (!detailsModal) {
            return false;
        }
        detailsModal.classList.remove('is-open');
        detailsModal.style.display = 'none';
        detailsModal.setAttribute('aria-hidden', 'true');
        return false;
    }

    function openDetails(details) {
        if (!detailsModal) {
            return;
        }
        var server = details.server || {};
        var checks = details.checks || [];
        var notifications = details.notifications || [];
        var title = document.getElementById('detailsTitle');
        var body = document.getElementById('detailsBody');
        if (title) {
            title.textContent = server.name || 'Details';
        }
        if (body) {
            var html = '<div class="details-summary">' +
                miniStat('Status', server.status || 'unknown') +
                miniStat('Uptime Score', (server.uptime_score || '0') + '%') +
                miniStat('Latenz', (server.response_time_ms || '-') + ' ms') +
                miniStat('Letzter Check', server.last_checked_at || '-') +
                '</div>';
            html += '<div class="table-wrap"><table class="data-table"><thead><tr><th>Zeit</th><th>Status</th><th>Latenz</th><th>HTTP</th><th>Fehler</th></tr></thead><tbody>';
            checks.forEach(function (checkRow) {
                html += '<tr><td>' + esc(checkRow.checked_at) + '</td><td><span class="status-pill ' + esc(checkRow.status) + '">' + esc(checkRow.status) + '</span></td><td>' + esc(checkRow.response_time_ms || '-') + ' ms</td><td>' + esc(checkRow.http_code || '-') + '</td><td>' + esc(checkRow.error_message || '-') + '</td></tr>';
            });
            if (!checks.length) {
                html += '<tr><td colspan="5">Noch keine Abfragen vorhanden.</td></tr>';
            }
            html += '</tbody></table></div>';
            html += '<h2>Benachrichtigungen</h2><div class="activity-list">';
            notifications.forEach(function (item) {
                html += '<div class="activity-item"><span class="dot ' + (item.status === 'sent' ? 'up' : 'down') + '"></span><div><strong>' + esc(item.subject) + '</strong><small>' + esc(item.created_at) + ' / ' + esc(item.recipient) + '</small></div></div>';
            });
            if (!notifications.length) {
                html += '<p class="muted">Keine Benachrichtigungen vorhanden.</p>';
            }
            html += '</div>';
            body.innerHTML = html;
        }
        detailsModal.classList.add('is-open');
        detailsModal.style.display = 'grid';
        detailsModal.setAttribute('aria-hidden', 'false');
    }

    function miniStat(label, value) {
        return '<div class="mini-stat"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-close-details]')).forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            closeDetails();
        });
    });

    if (detailsModal) {
        closeDetails();
        detailsModal.addEventListener('click', function (event) {
            if (event.target === detailsModal) {
                closeDetails();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
            closeDetails();
        }
    });

    if (table) {
        Array.prototype.slice.call(table.querySelectorAll('th[data-sort]')).forEach(function (header, index) {
            header.addEventListener('click', function () {
                sortState.dir = sortState.index === index ? sortState.dir * -1 : 1;
                sortState.index = index;
                applySort();
            });
        });
    }

    Array.prototype.slice.call(document.querySelectorAll('.side-nav a[href^="#"]')).forEach(function (link) {
        link.addEventListener('click', function (event) {
            var target = document.querySelector(link.getAttribute('href'));
            if (!target) {
                return;
            }
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', link.getAttribute('href'));
        });
    });

    if (search) {
        search.addEventListener('input', applyFilters);
    }
    if (filter) {
        filter.addEventListener('change', applyFilters);
    }

    setupActivityTable();

    window.setTimeout(drawDashboardCharts, 0);
    if (window.requestAnimationFrame) {
        window.requestAnimationFrame(drawDashboardCharts);
    }
    window.addEventListener('resize', function () {
        window.clearTimeout(drawDashboardCharts.timer);
        drawDashboardCharts.timer = window.setTimeout(drawDashboardCharts, 120);
    });

    function drawDashboardCharts() {
        var dataElement = document.getElementById('chartData');
        var data = [];
        if (dataElement) {
            try {
                data = JSON.parse(dataElement.textContent || '[]');
            } catch (error) {
                data = [];
            }
            drawLatencyChart(document.getElementById('latencyChart'), data);
            drawStatusChart(document.getElementById('statusChart'), data);
        }

        var publicDataElement = document.getElementById('publicChartData');
        var publicData = [];
        if (publicDataElement) {
            try {
                publicData = JSON.parse(publicDataElement.textContent || '[]');
            } catch (error) {
                publicData = [];
            }
            drawLatencyChart(document.getElementById('publicLatencyChart'), publicData);
            drawStatusChart(document.getElementById('publicStatusChart'), publicData);
        }
    }

    function setupCanvas(canvas) {
        if (!canvas) {
            return null;
        }
        var ratio = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        var cssWidth = rect.width || canvas.parentNode.clientWidth || 420;
        var cssHeight = rect.height || Number(canvas.getAttribute('height')) || 170;
        canvas.width = Math.max(320, Math.floor(cssWidth * ratio));
        canvas.height = Math.floor(cssHeight * ratio);
        var ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        return { ctx: ctx, width: canvas.width / ratio, height: canvas.height / ratio };
    }

    function drawLatencyChart(canvas, data) {
        var setup = setupCanvas(canvas);
        if (!setup) {
            return;
        }
        var ctx = setup.ctx;
        var width = setup.width;
        var height = setup.height;
        var area = { left: 50, right: 16, top: 16, bottom: 34 };
        var plotWidth = Math.max(1, width - area.left - area.right);
        var plotHeight = Math.max(1, height - area.top - area.bottom);
        ctx.clearRect(0, 0, width, height);
        var points = data.filter(function (row) {
            return row.response_time_ms !== null && row.response_time_ms !== undefined && row.response_time_ms !== '';
        }).slice(-40);
        if (!points.length) {
            ctx.fillStyle = '#95a3b8';
            ctx.fillText('Noch keine Daten', 12, 28);
            canvas.__serverMonitorChartPoints = [];
            setupChartHover(canvas);
            return;
        }
        var rawMax = Math.max.apply(null, points.map(function (row) {
            return Number(row.response_time_ms) || 0;
        })) || 1;
        var max = niceMax(rawMax);
        var chartPoints = [];

        ctx.font = '11px Inter, sans-serif';
        ctx.textBaseline = 'middle';
        ctx.strokeStyle = '#263244';
        ctx.fillStyle = '#95a3b8';
        ctx.lineWidth = 1;

        for (var g = 0; g <= 4; g += 1) {
            var value = max - (max / 4) * g;
            var y = area.top + (plotHeight / 4) * g;
            ctx.beginPath();
            ctx.moveTo(area.left, y);
            ctx.lineTo(width - area.right, y);
            ctx.stroke();
            ctx.textAlign = 'right';
            ctx.fillText(String(Math.round(value)) + ' ms', area.left - 8, y);
        }

        ctx.beginPath();
        ctx.moveTo(area.left, area.top);
        ctx.lineTo(area.left, area.top + plotHeight);
        ctx.lineTo(width - area.right, area.top + plotHeight);
        ctx.stroke();

        ctx.textAlign = 'left';
        ctx.fillText('ms', area.left, 8);
        ctx.textAlign = 'right';
        ctx.fillText('Zeit', width - area.right, height - 8);

        drawTimeLabel(ctx, points[0], area.left, height - 18, 'left');
        drawTimeLabel(ctx, points[Math.floor(points.length / 2)], area.left + plotWidth / 2, height - 18, 'center');
        drawTimeLabel(ctx, points[points.length - 1], width - area.right, height - 18, 'right');

        ctx.strokeStyle = '#5dd6a5';
        ctx.lineWidth = 2;
        ctx.beginPath();
        points.forEach(function (row, index) {
            var x = area.left + (points.length === 1 ? plotWidth / 2 : index * (plotWidth / (points.length - 1)));
            var y = area.top + plotHeight - ((Number(row.response_time_ms) || 0) / max) * plotHeight;
            chartPoints.push({
                x: x,
                y: y,
                latency: Number(row.response_time_ms) || 0,
                checkedAt: row.checked_at || '',
                name: row.name || ''
            });
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();

        ctx.fillStyle = '#5dd6a5';
        chartPoints.forEach(function (point) {
            ctx.beginPath();
            ctx.arc(point.x, point.y, 2.5, 0, Math.PI * 2);
            ctx.fill();
        });

        canvas.__serverMonitorChartPoints = chartPoints;
        setupChartHover(canvas);
    }

    function drawStatusChart(canvas, data) {
        var setup = setupCanvas(canvas);
        if (!setup) {
            return;
        }
        var ctx = setup.ctx;
        var width = setup.width;
        var height = setup.height;
        var area = { left: 42, right: 16, top: 16, bottom: 30 };
        var plotHeight = Math.max(1, height - area.top - area.bottom);
        var up = data.filter(function (row) { return row.status === 'up'; }).length;
        var down = data.filter(function (row) { return row.status === 'down'; }).length;
        var total = Math.max(1, up + down);
        var max = niceMax(Math.max(up, down, 1));
        var bars = [
            { label: 'Online', value: up, color: '#5dd6a5' },
            { label: 'Offline', value: down, color: '#ff6b78' }
        ];
        ctx.clearRect(0, 0, width, height);
        ctx.font = '11px Inter, sans-serif';
        ctx.strokeStyle = '#263244';
        ctx.fillStyle = '#95a3b8';
        ctx.lineWidth = 1;
        for (var g = 0; g <= 4; g += 1) {
            var value = max - (max / 4) * g;
            var y = area.top + (plotHeight / 4) * g;
            ctx.beginPath();
            ctx.moveTo(area.left, y);
            ctx.lineTo(width - area.right, y);
            ctx.stroke();
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(Math.round(value)), area.left - 8, y);
        }
        ctx.beginPath();
        ctx.moveTo(area.left, area.top);
        ctx.lineTo(area.left, area.top + plotHeight);
        ctx.lineTo(width - area.right, area.top + plotHeight);
        ctx.stroke();
        ctx.textAlign = 'left';
        ctx.fillText('Checks', area.left, 8);
        bars.forEach(function (bar, index) {
            var barHeight = (bar.value / max) * plotHeight;
            var x = area.left + 32 + index * 110;
            var y = area.top + plotHeight - barHeight;
            ctx.fillStyle = bar.color;
            ctx.fillRect(x, y, 58, barHeight);
            ctx.fillStyle = '#edf3fb';
            ctx.fillText(String(bar.value), x + 20, y - 8);
            ctx.fillStyle = '#95a3b8';
            ctx.fillText(bar.label, x + 4, height - 10);
        });
    }

    function niceMax(value) {
        value = Math.max(1, Number(value) || 1);
        var power = Math.pow(10, Math.floor(Math.log(value) / Math.LN10));
        var scaled = value / power;
        var nice = scaled <= 1 ? 1 : (scaled <= 2 ? 2 : (scaled <= 5 ? 5 : 10));
        return nice * power;
    }

    function drawTimeLabel(ctx, row, x, y, align) {
        if (!row) {
            return;
        }
        ctx.textAlign = align;
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#95a3b8';
        ctx.fillText(shortTime(row.checked_at || ''), x, y);
    }

    function shortTime(value) {
        var text = String(value || '');
        var match = text.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (match) {
            return match[4] + ':' + match[5];
        }
        return text.slice(0, 12);
    }

    function setupChartHover(canvas) {
        if (!canvas || canvas.__serverMonitorHoverReady) {
            return;
        }
        canvas.__serverMonitorHoverReady = true;
        canvas.addEventListener('mousemove', function (event) {
            var points = canvas.__serverMonitorChartPoints || [];
            if (!points.length) {
                hideChartTooltip();
                return;
            }
            var rect = canvas.getBoundingClientRect();
            var x = event.clientX - rect.left;
            var y = event.clientY - rect.top;
            var nearest = null;
            var best = Infinity;
            points.forEach(function (point) {
                var dx = point.x - x;
                var dy = point.y - y;
                var distance = Math.sqrt(dx * dx + dy * dy);
                if (distance < best) {
                    best = distance;
                    nearest = point;
                }
            });
            if (!nearest || best > 42) {
                hideChartTooltip();
                return;
            }
            showChartTooltip(event.clientX, event.clientY, nearest);
        });
        canvas.addEventListener('mouseleave', hideChartTooltip);
    }

    function chartTooltip() {
        var tooltip = document.getElementById('chartTooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'chartTooltip';
            tooltip.className = 'chart-tooltip';
            tooltip.hidden = true;
            document.body.appendChild(tooltip);
        }
        return tooltip;
    }

    function showChartTooltip(clientX, clientY, point) {
        var tooltip = chartTooltip();
        tooltip.innerHTML = '<strong>' + esc(point.latency) + ' ms</strong><span>' + esc(point.checkedAt) + '</span><small>' + esc(point.name) + '</small>';
        tooltip.hidden = false;
        tooltip.style.left = Math.min(clientX + 14, window.innerWidth - 190) + 'px';
        tooltip.style.top = Math.max(clientY - 54, 8) + 'px';
    }

    function hideChartTooltip() {
        var tooltip = document.getElementById('chartTooltip');
        if (tooltip) {
            tooltip.hidden = true;
        }
    }
})();
