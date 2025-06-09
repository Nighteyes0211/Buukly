function ensureModalExists() {
    if (!document.getElementById('buukly-modal')) {
        const overlay = document.createElement('div');
        overlay.id = 'buukly-modal-overlay';
        overlay.style = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9998;';
        overlay.addEventListener('click', () => {
            overlay.style.display = 'none';
            modal.style.display = 'none';
        });

        const modal = document.createElement('div');
        modal.id = 'buukly-modal';
        modal.style = 'display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:30px; border-radius:10px; z-index:9999; max-width:700px; width:90%; box-shadow:0 0 20px rgba(0,0,0,0.3);';

        const closeBtn = document.createElement('button');
        closeBtn.id = 'buukly-modal-close';
        closeBtn.textContent = '×';
        closeBtn.style = 'position:absolute; top:10px; right:15px; background:none; border:none; font-size:24px; cursor:pointer;';
        closeBtn.addEventListener('click', () => {
            overlay.style.display = 'none';
            modal.style.display = 'none';
        });

        const content = document.createElement('div');
        content.id = 'buukly-modal-content';

        modal.appendChild(closeBtn);
        modal.appendChild(content);
        document.body.appendChild(overlay);
        document.body.appendChild(modal);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const wrapper = document.querySelector('.buukly-calendar-wrapper');
    const button = document.getElementById('buukly-submit-location');

    if (!wrapper || !button) return;

    button.addEventListener('click', function () {
        setTimeout(() => {
            const selectedLocation = wrapper.querySelector('input[name="buukly_selected_location"]:checked');
            if (!selectedLocation) {
                alert('Bitte einen Standort auswählen.');
                return;
            }

            const locationId = selectedLocation.value;
            wrapper.innerHTML = '<p>Lade Kalender …</p>';

            fetch(buukly_ajax.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'buukly_get_availability',
                    location_id: locationId
                })
            })
            .then(response => response.text())
            .then(html => {
                wrapper.innerHTML = html;

                const calendarEl = document.getElementById('buukly-calendar');
                const locationInput = document.getElementById('buukly-location-id');
                if (!calendarEl || !locationInput) return;

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'de',
                    height: 'auto',
                    selectable: true,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    dateClick: function (info) {
                        const selectedDate = info.dateStr;

                        fetch(buukly_ajax.ajax_url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'buukly_get_employees',
                                location_id: locationInput.value,
                                date: selectedDate
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            const container = document.getElementById('buukly-employees-container');
                            if (!container) return;

                            if (!data.success || !data.data) {
                                container.innerHTML = '<p>Keine Mitarbeiter verfügbar.</p>';
                                return;
                            }

                            container.innerHTML = `
                                <label for="buukly-employee-select">Mitarbeiter auswählen:</label>
                                <select id="buukly-employee-select">
                                    <option disabled selected>Bitte auswählen</option>
                                    ${data.data}
                                </select>
                            `;

                            const slots = document.getElementById('buukly-slots-container');
                            if (slots) slots.innerHTML = '<p>Bitte zuerst einen Mitarbeiter wählen.</p>';

                            document.getElementById('buukly-employee-select').addEventListener('change', function () {
                                const employeeId = this.value;

                                fetch(buukly_ajax.ajax_url, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams({
                                        action: 'buukly_get_time_slots',
                                        date: selectedDate,
                                        location_id: locationInput.value,
                                        employee_id: employeeId
                                    })
                                })
                                .then(r => r.json())
                                .then(data => {
                                    const slotContainer = document.getElementById('buukly-slots-container');
                                    if (slotContainer && data.success) {
                                        slotContainer.innerHTML = data.data;

                                        document.querySelectorAll('.buukly-slot').forEach(button => {
                                            button.addEventListener('click', function () {
                                                const start = this.dataset.start;
                                                const end = this.dataset.end;
                                                const selectedDate = start.slice(0, 10);
                                                const employeeId = document.getElementById('buukly-employee-select').value;
                                                const locationId = document.getElementById('buukly-location-id').value;

                                                // Modal befüllen
                                                document.getElementById('buukly-modal-content').innerHTML = generateFormHTML(employeeId, locationId, selectedDate, start, end);
                                                document.getElementById('buukly-modal').style.display = 'block';
                                                document.getElementById('buukly-modal-overlay').style.display = 'block';

                                                // Initialisiere Multistep-Formular
                                                initMultistepForm();

                                                // Close-Button aktivieren
                                                document.getElementById('buukly-modal-close').onclick = function () {
                                                    document.getElementById('buukly-modal').style.display = 'none';
                                                    document.getElementById('buukly-modal-overlay').style.display = 'none';
                                                };
                                            });
                                        });
                                    }
                                })
                                .catch(error => console.error('Error fetching time slots:', error));
                            });
                        })
                        .catch(error => console.error('Error fetching employees:', error));
                    }
                });

                calendar.render();
            })
            .catch(error => console.error('Error fetching availability:', error));
        }, 0);
    });

    function generateFormHTML(employeeId, locationId, selectedDate, start, end) {
        return `
            <h3>Termin buchen: ${selectedDate} · ${start.slice(11, 16)} – ${end.slice(11, 16)}</h3>
            <form id="buukly-booking-form" class="container mt-4 mb-4">
                <input type="hidden" name="employee_id" value="${employeeId}">
                <input type="hidden" name="location_id" value="${locationId}">
                <input type="hidden" name="date" value="${selectedDate}">
                <input type="hidden" name="start_time" value="${start}">
                <input type="hidden" name="end_time" value="${end}">

                <div id="step-1" class="step">
                    <fieldset class="mb-4">
                        <legend class="h5">Persönliche Angaben</legend>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vorname*</label>
                                <input type="text" name="first_name" required class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nachname*</label>
                                <input type="text" name="last_name" required class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Titel</label>
                                <input type="text" name="title" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Geburtsdatum*</label>
                                <input type="date" name="birth_date" required class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Familienstand</label>
                                <input type="text" name="family_status" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Anschrift*</label>
                                <input type="text" name="address" required class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-2" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Kontakt</legend>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Telefon</label>
                                <input type="tel" name="phone" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Handy-Nr.</label>
                                <input type="tel" name="mobile" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">E-Mail</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Bankverbindung</label>
                                <input type="text" name="bank_info" class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-3" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Weitere Angaben</legend>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name des Ehepartners</label>
                                <input type="text" name="spouse_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Geburtsdatum des Ehepartners</label>
                                <input type="date" name="spouse_birth_date" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name des Kindes 1</label>
                                <input type="text" name="child_name_1" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Geburtsdatum des Kindes 1</label>
                                <input type="date" name="child_birth_1" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rechtsschutzversicherung</label>
                                <input type="text" name="legal_insurance" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Versicherungsnummer</label>
                                <input type="text" name="insurance_number" class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-4" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Gegenpartei</legend>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name der Gegenpartei</label>
                                <input type="text" name="opposing_party" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Adresse der Gegenpartei</label>
                                <input type="text" name="opposing_address" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefon der Gegenpartei</label>
                                <input type="tel" name="opposing_phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Handy der Gegenpartei</label>
                                <input type="tel" name="opposing_mobile" class="form-control">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Sonstige Informationen</label>
                                <textarea name="other_info" class="form-control"></textarea>
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="submit" class="btn btn-success">Termin buchen</button>
                </div>
            </form>
        `;
    }

    function initMultistepForm() {
        const form = document.getElementById('buukly-booking-form');
        const steps = document.querySelectorAll('.step');
        let currentStep = 0;

        function showStep(stepIndex) {
            steps.forEach((step, index) => {
                step.style.display = index === stepIndex ? 'block' : 'none';
            });
        }

        showStep(currentStep);

        form.addEventListener('click', function (e) {
            if (e.target.classList.contains('next-step')) {
                e.preventDefault();
                if (currentStep < steps.length - 1) {
                    currentStep++;
                    showStep(currentStep);
                }
            } else if (e.target.classList.contains('prev-step')) {
                e.preventDefault();
                if (currentStep > 0) {
                    currentStep--;
                    showStep(currentStep);
                }
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('action', 'buukly_send_booking');
            formData.append('_ajax_nonce', buukly_ajax.nonce);

            fetch(buukly_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(response => {
                if (response.success) {
                    form.innerHTML = `<p><strong>Vielen Dank!</strong> Ihre Buchung wurde übermittelt.</p>`;
                } else {
                    form.insertAdjacentHTML('beforeend', `<p style="color:red;">Fehler: ${response.data || 'Unbekannter Fehler'}</p>`);
                }
            })
            .catch(err => {
                console.error('Fehler beim Senden der Buchung:', err);
                form.insertAdjacentHTML('beforeend', `<p style="color:red;">Netzwerkfehler beim Senden der Buchung.</p>`);
            });
        });
    }
});