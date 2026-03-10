---
title: Iniziamo una conversazione
published: true
routable: true
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
    buttons:
        submit:
            type: submit
            value: Invia Messaggio
            classes: btn btn-primary
    process:
        email:
            from: "info@valentinarussobg5.com"
            to: "valentinebers@gmail.com"
            subject: "Nuovo messaggio da valentinarussobg5.com"
            body: "Nome: {{ form.value.name }}\nEmail: {{ form.value.email }}\n\nMessaggio:\n{{ form.value.message }}"
        message: Grazie! Il tuo messaggio è stato inviato correttamente.
        reset: true
---
