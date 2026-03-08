---
title: Candidatura Corporate
published: true
routable: true
corporate: true
form:
    name: contatti-aziende
    fields:
        nome:
            label: Nome o Referente
            type: text
            required: true
        azienda:
            label: Nome Azienda
            type: text
            required: true
        email:
            label: Indirizzo Email
            type: email
            required: true
        messaggio:
            label: Come posso aiutarti?
            type: textarea
            required: true
    buttons:
        submit:
            type: submit
            value: Invia Richiesta
    process:
        email:
            from: "info@valentinarussobg5.com"
            to: "valentinebers@gmail.com"
            subject: "Candidatura Corporate da valentinarussobg5.com"
            body: "Nome: {{ form.value.nome }}\nAzienda: {{ form.value.azienda }}\nEmail: {{ form.value.email }}\n\nMessaggio:\n{{ form.value.messaggio }}"
        message: Grazie! La tua richiesta è stata inviata. Ti contatteremo presto.
        reset: true
---
