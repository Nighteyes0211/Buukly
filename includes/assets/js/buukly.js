document.addEventListener('DOMContentLoaded', function () {
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
                                            // Aktiven Slot markieren
                                            document.querySelectorAll('.buukly-slot').forEach(btn => btn.classList.remove('active-slot'));
                                            this.classList.add('active-slot');

                                            const start = this.dataset.start;
                                            const end = this.dataset.end;
                                            const employeeId = document.getElementById('buukly-employee-select').value;
                                            const locationId = locationInput.value;

                                            const existing = document.getElementById('buukly-booking-form');
                                            if (existing) existing.parentElement.remove();

                                            const formHtml = `
                                                <div class="buukly-booking-form">
                                                    <h3>Termin buchen: ${selectedDate} · ${start.slice(11, 16)} – ${end.slice(11, 16)}</h3>
                                                    <form id="buukly-booking-form">
                                                        <input type="hidden" name="employee_id" value="${employeeId}">
                                                        <input type="hidden" name="location_id" value="${locationId}">
                                                        <input type="hidden" name="date" value="${selectedDate}">
                                                        <input type="hidden" name="start_time" value="${start}">
                                                        <input type="hidden" name="end_time" value="${end}">

                                                        <label>Vorname:<br><input type="text" name="first_name" required></label><br>
                                                        <label>Nachname:<br><input type="text" name="last_name" required></label><br>
                                                        <label>E-Mail:<br><input type="email" name="email" required></label><br>
                                                        <label>Telefon:<br><input type="text" name="phone"></label><br>
                                                        <label>Nachricht:<br><textarea name="message"></textarea></label><br>

                                                        <button type="submit">Termin buchen</button>
                                                    </form>
                                                </div>
                                            `;

                                            const formContainer = document.createElement('div');
                                            formContainer.innerHTML = formHtml;
                                            slotContainer.appendChild(formContainer);

                                            document.getElementById('buukly-booking-form').addEventListener('submit', function (e) {
                                                e.preventDefault();
                                                const form = this;
                                                const formData = new FormData(form);
                                                formData.append('action', 'buukly_send_booking');

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
                                        });
                                    });
                                } else if (slotContainer) {
                                    slotContainer.innerHTML = '<p>Keine Zeiten verfügbar.</p>';
                                }
                            })
                            .catch(err => {
                                console.error('Fehler beim Laden der Uhrzeiten:', err);
                                document.getElementById('buukly-slots-container').innerHTML = '<p>Fehler beim Laden der Uhrzeiten.</p>';
                            });
                        });
                    })
                    .catch(err => {
                        console.error('Fehler beim Laden der Mitarbeiter:', err);
                        document.getElementById('buukly-employees-container').innerHTML = '<p>Fehler beim Laden der Mitarbeiter.</p>';
                    });
                }
            });

            calendar.render();
        })
        .catch(err => {
            console.error('Fehler beim Laden des Kalenders:', err);
            wrapper.innerHTML = '<p>Fehler beim Laden des Kalenders.</p>';
        });
    });
});
