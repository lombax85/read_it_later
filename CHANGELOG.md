# Changelog

## [Unreleased]

### 15 Ottobre 2024

#### Aggiunto
- Funzionalità di chat con l'articolo
- Titolo per i podcast generati

#### Modificato
- Migliorato il comportamento dell'interfaccia utente su dispositivi mobili
- Ottimizzato lo User Agent per il crawling dei contenuti

### 14 Ottobre 2024

#### Aggiunto
- Visualizzazione della data di aggiunta per ogni link
- Funzionalità per segnare un link come non letto
- Funzionalità di ricerca per titolo o categoria

#### Modificato
- Migliorato il layout dei pulsanti nelle azioni dei link
- Implementata la possibilità di inserire contenuto manuale per link che bloccano i crawler

### 13 Ottobre 2024

#### Aggiunto
- Implementazione della funzionalità Docker per facilitare l'installazione e l'esecuzione del progetto
- Script di migrazione del database (`migrate.php`)
- Funzionalità di creazione podcast dai link selezionati

#### Modificato
- Aggiornato README.md con istruzioni per l'installazione usando Docker
- Migliorati gli stili CSS, in particolare per il pulsante di generazione del podcast
- Ottimizzato il comportamento del pulsante "Genera Podcast"

#### Corretto
- Risolti problemi di stile e layout in varie parti dell'applicazione
- Corretti errori nella gestione degli errori durante l'aggiunta di nuovi link

### 12 Ottobre 2024

#### Aggiunto
- Startup del progetto "Read It Later Never"
- Implementazione della struttura base del progetto:
  - `public/index.php`: Punto di ingresso dell'applicazione, gestisce le route e inizializza l'app
  - `public/index.html`: Interfaccia utente principale
  - `public/css/style.css`: Stili CSS per l'interfaccia utente
  - `public/js/app.js`: Logica JavaScript per l'interazione lato client
  - `src/Database.php`: Gestione della connessione al database SQLite
  - `src/Models/Link.php`: Modello per la gestione dei link
  - `src/Services/ContentExtractor.php`: Servizio per l'estrazione del contenuto dalle URL
  - `src/Services/SummaryGenerator.php`: Servizio per la generazione di riassunti utilizzando OpenAI
  - `src/Services/LinkCategorizer.php`: Servizio per la categorizzazione automatica dei link

#### Funzionalità iniziali
- Aggiunta e visualizzazione di link
- Generazione di riassunti per i link aggiunti
- Categorizzazione automatica dei link
- Interfaccia utente di base per l'interazione con l'applicazione

## [Note]
- Le date sono basate sui timestamp dei commit nel log Git fornito.
- Alcuni dettagli specifici potrebbero essere stati omessi se non chiaramente descritti nei messaggi di commit.
