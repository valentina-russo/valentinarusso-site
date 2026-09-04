---
title: Iniziamo una conversazione
published: true
routable: true
seo_title: "Prenota la tua Consulenza Human Design"
seo_desc: "Contatta Valentina Russo per una lettura Human Design o BG5®. Sessioni individuali per carriera, relazioni e amor proprio. Prenota la tua seduta."
form:
    name: contatti-privati
    fields:
        name:
            label: Nome Completo
            type: text
            required: true
        email:
            label: Email
            type: email
            required: true
        message:
            label: Messaggio / Tipo di Consulenza
            type: textarea
            required: true
        privacy:
            type: checkbox
            label: "Ho letto l'informativa privacy e acconsento al trattamento dei dati per rispondere alla mia richiesta."
            required: true
    buttons:
        submit:
            type: submit
            value: Invia Messaggio
            classes: btn btn-primary
    process:
        email:
            from: "info@valentinarussobg5.com"
            to: "info@valentinarussobg5.com"
            subject: "Nuovo messaggio da valentinarussobg5.com"
            body: "Nome: {{ form.value.name }}\nEmail: {{ form.value.email }}\n\nMessaggio:\n{{ form.value.message }}"
        message: Grazie! Il tuo messaggio è stato inviato correttamente.
        reset: true
---
