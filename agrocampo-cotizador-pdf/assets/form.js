(() => {
    const forms = document.querySelectorAll('.agrocampo-cotizador-form');
    if (!forms.length || !window.agrocampoCotizadorPdf) {
        return;
    }

    const restUrl = window.agrocampoCotizadorPdf.restUrl;

    forms.forEach((form) => {
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('.agrocampo-cotizador-status');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!button) {
                return;
            }

            const payload = {
                quote_number: form.elements.quote_number.value,
                date: form.elements.date.value,
                client: form.elements.client.value,
                attention: form.elements.attention.value,
                model: form.elements.model.value,
                rut: form.elements.rut.value,
                phone: form.elements.phone.value,
                email: form.elements.email.value,
                location: form.elements.location.value,
                subtitle: form.elements.subtitle.value,
                notes: form.elements.notes.value,
                contact_name: form.elements.contact_name.value,
                contact_title: form.elements.contact_title.value,
                contact_mobile: form.elements.contact_mobile.value,
                contact_phone: form.elements.contact_phone.value,
                contact_email: form.elements.contact_email.value,
            };

            try {
                payload.items = JSON.parse(form.elements.items.value || '[]');
            } catch (error) {
                if (status) {
                    status.textContent = 'El JSON de ítems no es válido.';
                }
                return;
            }

            const download = form.elements.download.checked;
            const endpoint = download ? `${restUrl}?download=1` : restUrl;

            button.disabled = true;
            if (status) {
                status.textContent = 'Generando PDF...';
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('Error al generar el PDF.');
                }

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'cotizacion-agrocampo.pdf';
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                if (status) {
                    status.textContent = 'PDF generado correctamente.';
                }
            } catch (error) {
                if (status) {
                    status.textContent = 'No se pudo generar el PDF.';
                }
            } finally {
                button.disabled = false;
            }
        });
    });
})();
