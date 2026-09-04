---
title: Candidatura Corporate
published: true
routable: true
corporate: true
seo_title: "Human Design per Aziende (BG5®), Contatti"
seo_desc: "Richiedi una consulenza Human Design applicata al business (metodo BG5®) per la tua azienda. Analisi partnership, team dynamics e selezione collaboratori."
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
        privacy:
            type: checkbox
            label: "Ho letto l'informativa privacy e acconsento al trattamento dei dati per rispondere alla mia richiesta."
            required: true
    buttons:
        submit:
            type: submit
            value: Invia Richiesta
            classes: btn btn-primary
    process:
        email:
            from: "info@valentinarussobg5.com"
            to: "info@valentinarussobg5.com"
            subject: "Candidatura Corporate da valentinarussobg5.com"
            body: "Nome: {{ form.value.nome }}\nAzienda: {{ form.value.azienda }}\nEmail: {{ form.value.email }}\n\nMessaggio:\n{{ form.value.messaggio }}"
        message: Grazie! La tua richiesta è stata inviata. Ti contatteremo presto.
        reset: true
---
