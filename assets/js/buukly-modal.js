// == MODAL SETUP ==
function ensureModalExists() {
    if (!document.getElementById('buukly-modal')) {
        const overlay = document.createElement('div');
        overlay.id = 'buukly-modal-overlay';
        overlay.style = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9998;';
        overlay.addEventListener('click', () => {
            overlay.style.display = 'none';
            const modal = document.getElementById('buukly-modal');
            if (modal) modal.style.display = 'none';
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
    ensureModalExists();

    const wrapper = document.querySelector('.buukly-calendar-wrapper');
    const button = document.getElementById('buukly-submit-location');

    if (!wrapper || !button) return;

    button.addEventListener('click', function () {
        const selectedLocation = wrapper.querySelector('input[name="buukly_selected_location"]:checked');
        if (!selectedLocation) return alert('Bitte einen Standort auswählen.');

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
                                            const date = start.slice(0, 10);
                                            const employeeId = document.getElementById('buukly-employee-select').value;
                                            const locationId = locationInput.value;

                                            openBookingModal(date, start, end, employeeId, locationId);
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
    });

    function openBookingModal(date, start, end, employeeId, locationId) {
        const modalOverlay = document.getElementById('buukly-modal-overlay');
        const modal = document.getElementById('buukly-modal');
        const modalContent = document.getElementById('buukly-modal-content');

        modalContent.innerHTML = `
            <h3>Termin buchen: ${date} · ${start.slice(11, 16)} – ${end.slice(11, 16)}</h3>
            <form id="buukly-booking-form" class="container mt-4 mb-4">
                <input type="hidden" name="employee_id" value="${employeeId}">
                <input type="hidden" name="location_id" value="${locationId}">
                <input type="hidden" name="date" value="${date}">
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
                        <legend class="h5">Ehegatte (optional)</legend>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Vorname</label>
                                <input type="text" name="spouse_first_name" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Nachname</label>
                                <input type="text" name="spouse_last_name" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Titel</label>
                                <input type="text" name="spouse_title" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Geburtsdatum</label>
                                <input type="date" name="spouse_birth_date" class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-4" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Kinder (optional)</legend>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="child_name_1" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Geburtsdatum</label>
                                <input type="date" name="child_birth_1" class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-5" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Mandantenstatus</legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="portal_access" value="ja" id="portal_access">
                            <label class="form-check-label" for="portal_access">Portal Zugang gewünscht</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="id_copy" value="ja" id="id_copy">
                            <label class="form-check-label" for="id_copy">Kopie Personalausweis beigelegt</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="existing_client" value="ja" id="existing_client">
                            <label class="form-check-label" for="existing_client">Ich bin bereits Mandant</label>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-6" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Wie wurden Sie aufmerksam?</legend>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="ref_homepage" value="homepage" id="ref_homepage">
                            <label class="form-check-label" for="ref_homepage">Homepage / Google</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="ref_recommendation" value="recommendation" id="ref_recommendation">
                            <label class="form-check-label" for="ref_recommendation">Empfehlung</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="ref_other" value="other" id="ref_other">
                            <label class="form-check-label" for="ref_other">Sonstiges</label>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-7" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Angaben zum Verfahren</legend>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rechtsschutzversicherung</label>
                                <input type="text" name="legal_insurance" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Versicherungs-Nr.</label>
                                <input type="text" name="insurance_number" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gegnerische Partei</label>
                                <input type="text" name="opposing_party" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Anschrift</label>
                                <input type="text" name="opposing_address" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefon</label>
                                <input type="tel" name="opposing_phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Handy</label>
                                <input type="tel" name="opposing_mobile" class="form-control">
                            </div>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="button" class="btn btn-primary next-step">Weiter</button>
                </div>

                <div id="step-8" class="step" style="display: none;">
                    <fieldset class="mb-4">
                        <legend class="h5">Sonstige Informationen</legend>
                        <div class="mb-3">
                            <label class="form-label">Weitere Informationen</label>
                            <textarea name="other_info" rows="5" class="form-control"></textarea>
                        </div>
                    </fieldset>
                    <button type="button" class="btn btn-secondary prev-step">Zurück</button>
                    <button type="submit" class="btn btn-success">Formular absenden</button>
                </div>
            </form>
        `;

        modalOverlay.style.display = 'block';
        modal.style.display = 'block';

        // Initialize multistep form
        initializeMultistepForm();
    }

    function initializeMultistepForm() {
        const steps = document.querySelectorAll('.step');
        const prevButtons = document.querySelectorAll('.prev-step');
        const nextButtons = document.querySelectorAll('.next-step');
        const form = document.getElementById('buukly-booking-form');
        let currentStep = 0;

        function showStep(stepIndex) {
            steps.forEach((step, index) => {
                step.style.display = index === stepIndex ? 'block' : 'none';
            });
        }

        showStep(currentStep);

        nextButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (validateStep(currentStep)) {
                    currentStep++;
                    showStep(currentStep);
                }
            });
        });

        prevButtons.forEach(button => {
            button.addEventListener('click', () => {
                currentStep--;
                showStep(currentStep);
            });
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (validateStep(currentStep)) {
                submitForm(form);
            }
        });

        function validateStep(stepIndex) {
            const step = steps[stepIndex];
            const requiredFields = step.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            return isValid;
        }

        function submitForm(formElement) {
            const formData = new FormData(formElement);
            const formValues = Object.fromEntries(formData.entries());

            fetch(buukly_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'buukly_submit_booking',
                    ...formValues
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Buchung erfolgreich!');
                    document.getElementById('buukly-modal-overlay').style.display = 'none';
                    document.getElementById('buukly-modal').style.display = 'none';
                } else {
                    alert('Fehler bei der Buchung: ' + data.message);
                }
            })
            .catch(error => console.error('Error submitting form:', error));
        }
    }
});