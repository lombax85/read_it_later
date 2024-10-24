document.addEventListener('DOMContentLoaded', function() {
    fetchLinks();
    fetchPodcasts();
    handleResize();
    changePodcast();
    initializeAccordion();
    initializePushToTalk();
    initializeCustomPlayPauseButton();
    initializeChatButton();

    const searchInput = document.getElementById('searchInput');
    const starFilterOptions = document.querySelectorAll('.star-filter-option');
    const starFilterLabel = document.getElementById('star-filter-label');

    searchInput.addEventListener('input', filterLinks);
    starFilterOptions.forEach(option => {
        option.addEventListener('click', function() {
            const value = parseInt(this.dataset.value);
            starFilterOptions.forEach(opt => {
                if (parseInt(opt.dataset.value) <= value) {
                    opt.classList.add('active');
                } else {
                    opt.classList.remove('active');
                }
            });
            filterLinks();
        });
    });

    starFilterLabel.addEventListener('click', function() {
        starFilterOptions.forEach(opt => opt.classList.remove('active'));
        filterLinks();
    });

    const chatForm = document.getElementById('chatForm');
    chatForm.addEventListener('submit', handleChatSubmit);

    // Rimuovi questa riga duplicata
    // const starFilterOptions = document.querySelectorAll('.star-filter-option');
});

let selectedLinks = [];
let allLinks = [];
let currentChatLinkId = null;
let chatHistory = [];
let chatMemory = {};
let mediaRecorder;
let audioChunks = [];
let voiceConversationHistory = [];
let readTimeout = [];

function fetchLinks() {
    fetch('./api/links')
        .then(response => response.json())
        .then(links => {
            console.log('Links received from backend:', links);
            allLinks = links; // Salva tutti i link
            filterLinks(); // Usa filterLinks invece di renderLinks direttamente
            updateGeneratePodcastButton();
            initializeReadLinksAccordion();
        })
        .catch(error => console.error('Errore nel recupero dei link:', error));
}

function filterLinks() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const starRating = document.querySelectorAll('.star-filter-option.active').length;

    const filteredLinks = allLinks.filter(link => 
        (link.title.toLowerCase().includes(searchTerm) ||
        link.category.toLowerCase().includes(searchTerm)) &&
        (starRating === 0 || link.ranking >= starRating)
    );
    renderLinks(filteredLinks);
}

function renderLinks(links) {
    const unreadLinkList = document.getElementById('unread-link-list');
    const readLinkList = document.getElementById('read-link-list');
    unreadLinkList.innerHTML = '';
    readLinkList.innerHTML = '';
    
    links.forEach(link => {
        const linkElement = createLinkElement(link);
        if (link.isRead) {
            readLinkList.appendChild(linkElement);
        } else {
            unreadLinkList.appendChild(linkElement);
        }
    });
}

function createLinkElement(link) {
    console.log('Creating element for link:', link);
    const div = document.createElement('div');
    div.className = 'link-item';
    div.dataset.id = link.id;
    if (link.isRead) {
        div.classList.add('read');
    }
    
    // Formatta la data
    const createdAt = new Date(link.createdAt);
    const formattedDate = `${createdAt.getDate().toString().padStart(2, '0')}/${(createdAt.getMonth() + 1).toString().padStart(2, '0')}/${createdAt.getFullYear()} ${createdAt.getHours().toString().padStart(2, '0')}:${createdAt.getMinutes().toString().padStart(2, '0')}`;

    const starsHtml = createStarRating(link.id, link.ranking);

    div.innerHTML = `
        <div class="link-header">
            <label class="link-checkbox">
                <input type="checkbox" onchange="toggleLinkSelection(${link.id}, '${link.url}')">
                <span class="checkmark"></span>
            </label>
            <h3>${link.title || 'Titolo non disponibile'}</h3>
        </div>
        <div class="link-info">
            <span class="link-category">${link.category || 'Categoria non disponibile'}</span>
            <span class="link-date">Aggiunto il: ${formattedDate}</span>
            <a href="${link.url || '#'}" target="_blank" class="link-url">${link.url ? 'Visita il link' : 'URL non disponibile'}</a>
            <div class="star-rating" data-link-id="${link.id}">${starsHtml}</div>
        </div>
        <div class="link-actions">
            <button onclick="generateOrShowSummary(${link.id}, '${link.url || ''}', ${link.isRead})">
                ${link.summary ? 'Mostra Riassunto' : 'Genera Riassunto'}
            </button>
            ${link.isRead ? `<button onclick="markLinkAsUnread(${link.id})">Segna come non letto</button>` : ''}
            <button onclick="deleteLink(${link.id})">Elimina</button>
            <button onclick="openChatModal(${link.id})">Chatta con l'articolo</button>
        </div>
        <div id="accordion-item-${link.id}" class="accordion-item"></div>
    `;
    return div;
}

function createStarRating(linkId, currentRanking) {
    let html = '';
    for (let i = 1; i <= 3; i++) {
        html += `<span class="${i <= currentRanking ? 'active' : ''}" onclick="updateRanking(${linkId}, ${i})">☆</span>`;
    }
    return html;
}

function updateRanking(linkId, newRanking) {
    fetch(`./api/links/${linkId}/ranking`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ranking: newRanking })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const starRating = document.querySelector(`.star-rating[data-link-id="${linkId}"]`);
            starRating.innerHTML = createStarRating(linkId, newRanking);
            // Aggiorna il ranking nel array allLinks
            const linkIndex = allLinks.findIndex(link => link.id === linkId);
            if (linkIndex !== -1) {
                allLinks[linkIndex].ranking = newRanking;
            }
            // Applica i filtri correnti
            filterLinks();
        }
    })
    .catch(error => console.error('Errore nell\'aggiornamento del ranking:', error));
}

function toggleLinkSelection(id, url) {
    const index = selectedLinks.findIndex(link => link.id === id);
    if (index === -1) {
        selectedLinks.push({ id, url });
    } else {
        selectedLinks.splice(index, 1);
    }
    updateGeneratePodcastButton();
}

function updateGeneratePodcastButton() {
    const button = document.getElementById('generatePodcastButton');
    if (selectedLinks.length > 0) {
        button.style.display = 'inline-block';
        button.classList.add('generate-podcast-button');
    } else {
        button.style.display = 'none';
        button.classList.remove('generate-podcast-button');
    }
}

function generatePodcast(length, language) {
    // Mostra l'icona di attesa
    const loadingIcon = document.getElementById('loadingIcon');
    loadingIcon.style.display = 'block';

    // Chiudi la modale
    document.getElementById('generatePodcastModal').style.display = 'none';
    document.getElementById('generatePodcastForm').reset();

    fetch('./api/generate-podcast', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            links: selectedLinks,
            length: length,
            language: language
        }),
    })
    .then(response => response.json())
    .then(data => {
        // Simula un ritardo per dare l'illusione di una generazione in background
        setTimeout(() => {
            // Nascondi l'icona di attesa
            loadingIcon.style.display = 'none';

            // Aggiorna la lista dei podcast
            fetchPodcasts();

            alert('Podcast generato con successo!');
        }, 5000); // Ritardo di 5 secondi, puoi regolarlo come preferisci
    })
    .catch(error => {
        console.error('Errore nella generazione del podcast:', error);
        // Nascondi l'icona di attesa anche in caso di errore
        loadingIcon.style.display = 'none';
        alert('Si è verificato un errore durante la generazione del podcast. Riprova più tardi.');
    });
}

function updatePodcastPlayer(newPodcastUrl) {
    const podcastPlayer = document.getElementById('podcast-player');
    const audioPlayer = document.getElementById('audio-player');
    const podcastSelect = document.getElementById('podcast-select');

    podcastPlayer.style.display = 'block';
    audioPlayer.src = newPodcastUrl;

    // Aggiungi il nuovo podcast alla lista
    const option = document.createElement('option');
    option.value = newPodcastUrl;
    option.textContent = `Podcast ${podcastSelect.options.length + 1}`;
    podcastSelect.appendChild(option);
    podcastSelect.value = newPodcastUrl;
}

function changePodcast() {
    const podcastSelect = document.getElementById('podcast-select');
    const audioPlayer = document.getElementById('audio-player');
    const deleteButton = document.getElementById('delete-podcast-button');
    
    audioPlayer.src = podcastSelect.value;
    
    // Abilita o disabilita il pulsante di eliminazione in base alla selezione
    if (deleteButton) {
        deleteButton.disabled = !podcastSelect.value;
    }
}

function generateOrShowSummary(id, url, isRead) {
    if (url) {
        fetch(`./api/summary/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.summary) {
                showSummaryInAccordion(id, data.summary);
                if (!isRead) {
                    readTimeout[id] = setTimeout(() => {
                        markLinkAsRead(id);
                    }, 10000);
                }
            } else {
                generateAndSaveSummary(id, url);
            }
        })
        .catch(error => {
            console.error('Errore nel recupero del riassunto:', error);
            generateAndSaveSummary(id, url);
        });
    } else {
        console.error('URL non disponibile per il link con ID:', id);
    }
}

function markLinkAsRead(id) {
    fetch(`./api/links/${id}/read`, {
        method: 'POST',
    })
    .then(response => response.json())
    .then(() => {
        // Non facciamo nulla qui, il cambiamento visivo avverrà al refresh
    })
    .catch(error => console.error('Errore nella marcatura del link come letto:', error));
}

function showSummaryInAccordion(id, summary) {
    const accordionItem = document.getElementById(`accordion-item-${id}`);
    
    // Rimuovi il contenuto esistente se presente
    accordionItem.innerHTML = '';

    accordionItem.innerHTML = `
        <div class="accordion-header" onclick="toggleAccordion(${id})">
            <h3>Riassunto</h3>
            <span class="accordion-icon">▼</span>
        </div>
        <div class="accordion-content">
            <div class="markdown-content">${marked.parse(summary)}</div>
        </div>
    `;

    // Apri l'accordion immediatamente
    const content = accordionItem.querySelector('.accordion-content');
    accordionItem.classList.add('active');
    content.style.maxHeight = content.scrollHeight + 'px';

}

function toggleAccordion(id) {
    const item = document.getElementById(`accordion-item-${id}`);
    const content = item.querySelector('.accordion-content');
    const allItems = document.querySelectorAll('.accordion-item');

    // Cancella il timer di lettura
    if (readTimeout[id]) {
        clearTimeout(readTimeout[id]);
    }

    // Apri o chiudi l'accordion corrente
    if (item.classList.contains('active')) {
        item.classList.remove('active');
        content.style.maxHeight = '0';
    } else {
        item.classList.add('active');
        content.style.maxHeight = content.scrollHeight + 'px';
    }
}

function generateAndSaveSummary(id, url) {
    fetch(`./api/summary/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ content: url }),
    })
    .then(response => response.json())
    .then(data => {
        showSummaryInAccordion(id, data.summary);
        markLinkAsRead(id);
    })
    .catch(error => console.error('Errore nella generazione del riassunto:', error));
}

function showAddLinkModal() {
    const modal = document.getElementById('addLinkModal');
    modal.style.display = 'block';

    const closeBtn = modal.querySelector('.close');
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}

function toggleContentAccordion() {
    const accordion = document.getElementById('manualContentAccordion');
    const modal = document.getElementById('addLinkModal');
    const modalContent = modal.querySelector('.modal-content');

    accordion.classList.toggle('active');

    if (accordion.classList.contains('active')) {
        accordion.style.display = 'block';
        accordion.style.maxHeight = accordion.scrollHeight + "px";
        modalContent.scrollTop = modalContent.scrollHeight;
    } else {
        accordion.style.maxHeight = "0";
        setTimeout(() => {
            accordion.style.display = 'none';
        }, 300);
    }
}

// Aggiungi questa funzione per inizializzare l'accordion chiuso
function initializeAccordion() {
    const accordion = document.getElementById('manualContentAccordion');
    accordion.style.display = 'none';
}

function initializeReadLinksAccordion() {
    const readLinksSection = document.getElementById('read-links-section');
    const readLinksToggle = document.getElementById('read-links-toggle');
    const readLinkList = document.getElementById('read-link-list');

    readLinksToggle.addEventListener('click', () => {
        if (readLinkList.style.display === 'none') {
            readLinkList.style.display = 'block';
            readLinksToggle.textContent = 'Nascondi link letti';
        } else {
            readLinkList.style.display = 'none';
            readLinksToggle.textContent = 'Mostra link letti';
        }
    });
}

document.getElementById('addLinkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const url = document.getElementById('url').value;
    const summaryLength = document.getElementById('summaryLength').value;
    const language = document.getElementById('language').value;
    const manualContent = document.getElementById('manualContent').value;

    addAndSummarizeLink(url, summaryLength, language, manualContent);
});

function addAndSummarizeLink(url, summaryLength, language, manualContent) {
    // Mostra l'icona di attesa
    const loadingIcon = document.createElement('div');
    loadingIcon.id = 'loadingIcon';
    loadingIcon.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aggiunta in corso...';
    document.body.appendChild(loadingIcon);

    fetch('./api/add-and-summarize', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            url: url,
            summaryLength: summaryLength,
            language: language,
            manualContent: manualContent || null // Modifica qui
        }),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Errore nella risposta del server');
        }
        return response.json();
    })
    .then(data => {
        // Rimuovi l'icona di attesa
        document.body.removeChild(loadingIcon);

        // Chiudi la modale
        document.getElementById('addLinkModal').style.display = 'none';
        document.getElementById('addLinkForm').reset();

        // Aggiorna la lista dei link invece di ricaricare la pagina
        fetchLinks();
    })
    .catch(error => {
        console.error('Errore nell\'aggiunta del link e generazione del riassunto:', error);
        // Rimuovi l'icona di attesa anche in caso di errore
        document.body.removeChild(loadingIcon);
        alert('Si è verificato un errore durante l\'aggiunta del link. Il link potrebbe essere stato aggiunto, ma si è verificato un problema durante la generazione del riassunto. Riprova più tardi o controlla la lista dei link.');
        // Aggiorna comunque la lista dei link
        fetchLinks();
    });
}

function deleteLink(id) {
    if (confirm('Sei sicuro di voler eliminare questo link?')) {
        fetch(`./api/links/${id}`, {
            method: 'DELETE',
        })
        .then(response => {
            if (response.ok) {
                fetchLinks(); // Aggiorna la lista dei link
            } else {
                throw new Error('Errore nella cancellazione del link');
            }
        })
        .catch(error => console.error('Errore nella cancellazione del link:', error));
    }
}

function fetchPodcasts() {
    fetch('./api/podcasts')
        .then(response => response.json())
        .then(podcasts => {
            updatePodcastList(podcasts);
        })
        .catch(error => console.error('Errore nel recupero dei podcast:', error));
}

function updatePodcastList(podcasts) {
    const podcastSelect = document.getElementById('podcast-select');
    const podcastSection = document.getElementById('podcast-section');
    const audioPlayer = document.getElementById('audio-player');
    
    if (podcasts.length > 0) {
        podcastSection.style.display = 'block';
        podcastSelect.innerHTML = '<option value="">Seleziona un podcast</option>';
        podcasts.forEach((podcast, index) => {
            const option = document.createElement('option');
            option.value = podcast.url;
            const date = new Date(podcast.date);
            const formattedDate = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
            option.textContent = `${podcast.title} - ${formattedDate}`;
            podcastSelect.appendChild(option);
        });
        
        // Seleziona l'ultimo podcast di default
        podcastSelect.value = podcasts[podcasts.length - 1].url;
        audioPlayer.src = podcastSelect.value;

    } else {
        podcastSection.style.display = 'none';
        audioPlayer.src = '';
    }
}

function deletePodcast() {
    const podcastSelect = document.getElementById('podcast-select');
    const selectedPodcast = podcastSelect.value;
    
    if (!selectedPodcast) {
        alert('Seleziona un podcast da eliminare');
        return;
    }

    if (confirm('Sei sicuro di voler eliminare questo podcast?')) {
        fetch(`./api${selectedPodcast}`, {
            method: 'DELETE'
        })
        .then(response => {
            if (response.ok) {
                alert('Podcast eliminato con successo');
                fetchPodcasts(); // Aggiorna la lista dei podcast
                document.getElementById('audio-player').src = ''; // Resetta il player audio
            } else {
                alert('Errore durante l\'eliminazione del podcast');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            alert('Si è verificato un errore durante l\'eliminazione del podcast');
        });
    }
}

function showGeneratePodcastModal() {
    const modal = document.getElementById('generatePodcastModal');
    modal.style.display = 'block';

    const closeBtn = modal.querySelector('.close');
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}

document.getElementById('generatePodcastForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const length = document.getElementById('podcastLength').value;
    const language = document.getElementById('podcastLanguage').value;

    generatePodcast(length, language);
});

// Aggiungi questa funzione per gestire il ridimensionamento della finestra
function handleResize() {
    const width = window.innerWidth;
    const linkItems = document.querySelectorAll('.link-item');
    linkItems.forEach(item => {
        if (width <= 768) {
            item.classList.add('mobile-view');
        } else {
            item.classList.remove('mobile-view');
        }
    });
}

// Aggiungi questi listener
window.addEventListener('resize', handleResize);

function markLinkAsUnread(id) {
    fetch(`./api/links/${id}/unread`, {
        method: 'POST',
    })
    .then(response => response.json())
    .then(() => {
        // Aggiorniamo la lista dei link per riflettere il cambiamento
        fetchLinks();
    })
    .catch(error => console.error('Errore nella marcatura del link come non letto:', error));
}

// Aggiungi questa funzione per aprire la modale di chat
function openChatModal(linkId) {
    currentChatLinkId = linkId;
    if (!chatMemory[linkId]) {
        chatMemory[linkId] = [];
    }
    const modal = document.getElementById('chatModal');
    modal.style.display = 'block';
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = '';
    
    // Popola la chat con i messaggi memorizzati
    chatMemory[linkId].forEach(message => {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', `${message.sender}-message`);
        messageElement.textContent = message.content;
        chatMessages.appendChild(messageElement);
    });

    // Scorri alla fine della chat
    chatMessages.scrollTop = chatMessages.scrollHeight;

    const closeBtn = modal.querySelector('.close');
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}

// Aggiungi questa funzione per gestire l'invio dei messaggi
function handleChatSubmit(event) {
    event.preventDefault();
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (message) {
        addMessageToChat('user', message);
        sendChatMessage(message);
        input.value = '';
    }
}

// Funzione per aggiungere un messaggio alla chat
function addMessageToChat(sender, message) {
    const chatMessages = document.getElementById('chatMessages');
    const messageElement = document.createElement('div');
    messageElement.classList.add('chat-message', `${sender}-message`);
    messageElement.textContent = message;
    chatMessages.appendChild(messageElement);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Aggiungi il messaggio alla memoria della chat solo se non esiste già
    if (currentChatLinkId) {
        const lastMessage = chatMemory[currentChatLinkId][chatMemory[currentChatLinkId].length - 1];
        if (!lastMessage || lastMessage.content !== message) {
            chatMemory[currentChatLinkId].push({ sender, content: message });
        }
    }
}

// Funzione per inviare un messaggio al server
function sendChatMessage(message) {
    fetch('./api/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            linkId: currentChatLinkId,
            message: message,
            history: chatMemory[currentChatLinkId].map(msg => ({
                role: msg.sender === 'user' ? 'user' : 'assistant',
                content: msg.content
            }))
        }),
    })
    .then(response => response.json())
    .then(data => {
        addMessageToChat('ai', data.reply);
        // Non aggiungiamo più il messaggio alla memoria qui, lo fa già addMessageToChat
    })
    .catch(error => console.error('Errore nell\'invio del messaggio:', error));
}

function initializePushToTalk() {
    const pushToTalkButton = document.getElementById('push-to-talk');
    const audioPlayer = document.getElementById('audio-player');
    let isRecording = false;
    let audioChunks = [];
    let mediaRecorder = null;

    pushToTalkButton.addEventListener('click', toggleRecording);

    async function toggleRecording() {
        if (isRecording) {
            stopRecording();
        } else {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                startRecording(stream);
            } catch (error) {
                console.error('Errore nell\'accesso al microfono:', error);
                alert("Per utilizzare la funzione di registrazione vocale, è necessario concedere l'autorizzazione all'uso del microfono.");
            }
        }
    }

    function startRecording(stream) {
        const responsePlayer = document.getElementById('response-player');
        const chatSpinner = document.getElementById('chat-spinner');

        mediaRecorder = new MediaRecorder(stream);

        mediaRecorder.ondataavailable = event => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };

        mediaRecorder.onstart = () => {
            console.log('MediaRecorder started', mediaRecorder.state);
            isRecording = true;
            chatSpinner.style.display = 'block';
            pushToTalkButton.classList.add('recording');
            pushToTalkButton.innerHTML = '<i class="fas fa-stop"></i>';
            audioChunks = [];
        };

        mediaRecorder.onstop = () => {
            console.log('MediaRecorder stopped', mediaRecorder.state);
            isRecording = false;
            pushToTalkButton.classList.remove('recording');
            pushToTalkButton.innerHTML = '<i class="fas fa-microphone"></i>';

            const audioBlob = new Blob(audioChunks, { type: 'audio/mp4' });
            console.log('Audio Blob size:', audioBlob.size);
            if (audioBlob.size > 0) {
                const audioURL = URL.createObjectURL(audioBlob);
                console.log('Audio URL created:', audioURL);
                sendAudioToServer(audioBlob);
            } else {
                console.error('Nessun audio registrato');
                alert('Nessun audio registrato. Assicurati che il microfono sia funzionante e che l\'autorizzazione sia stata concessa.');
            }
        };

        mediaRecorder.start(1000); // Registra in chunk di 1 secondo
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            console.log('Stopping MediaRecorder');
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
    }

    function sendAudioToServer(audioBlob) {
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.wav');
        
        const podcastSelect = document.getElementById('podcast-select');
        const selectedPodcastId = podcastSelect.value;
        formData.append('podcastId', selectedPodcastId);

        // Aggiungi la cronologia della conversazione al FormData
        formData.append('conversationHistory', JSON.stringify(voiceConversationHistory));

        const timeoutId = setTimeout(() => {
            alert('La richiesta sta impiegando più tempo del previsto. Riprova più tardi.');
        }, 30000);

        fetch('/api/process-audio', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            clearTimeout(timeoutId);
            if (data.audioUrl) {
                fetch(data.audioUrl, { method: 'HEAD' })
                    .then(response => {
                        if (response.ok) {
                            console.log('File audio trovato, avvio riproduzione');
                            playResponse(data.audioUrl);
                            // Aggiungiamo la domanda e la risposta alla cronologia
                            voiceConversationHistory.push({ role: 'user', content: data.userTranscription });
                            voiceConversationHistory.push({ role: 'assistant', content: data.aiResponse });
                            console.log('Cronologia aggiornata:', voiceConversationHistory);
                        } else {
                            throw new Error('File audio non trovato');
                        }
                    })
                    .catch(error => {
                        console.error('Errore nel controllo del file audio:', error);
                        alert('Si è verificato un errore nel caricamento dell\'audio. Riprova.');
                    });
            } else {
                throw new Error('URL audio non ricevuto dal server');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Errore nell\'invio dell\'audio:', error);
            alert('Si è verificato un errore durante l\'elaborazione dell\'audio. Riprova.');
        });
    }

    function playResponse(audioUrl) {
        const responsePlayer = document.getElementById('response-player');
        const chatPlayButton = document.getElementById('chat-play');
        const chatSpinner = document.getElementById('chat-spinner');
        
        // Mostra lo spinner
        chatSpinner.style.display = 'block';
        
        const noCacheAudioUrl = audioUrl + '?t=' + new Date().getTime();
        responsePlayer.src = noCacheAudioUrl;
        
        console.log('Audio URL:', noCacheAudioUrl);
        
        responsePlayer.onerror = function() {
            console.error('Errore durante il caricamento dell\'audio:', responsePlayer.error);
            chatSpinner.style.display = 'none';
            chatPlayButton.disabled = true;
            alert('Si è verificato un errore durante il caricamento dell\'audio. Riprova.');
        };
        
        responsePlayer.onloadedmetadata = function() {
            console.log('Metadata dell\'audio caricati');
            chatSpinner.style.display = 'none';
            chatPlayButton.disabled = false;
        };
        
        responsePlayer.oncanplaythrough = function() {
            console.log('Audio pronto per la riproduzione');
            chatSpinner.style.display = 'none';
            chatPlayButton.disabled = false;
            responsePlayer.play().then(() => {
                console.log('Riproduzione avviata con successo');
                chatPlayButton.innerHTML = '<i class="fas fa-pause"></i>';
            }).catch(function(error) {
                console.error('Errore durante la riproduzione dell\'audio:', error);
                chatPlayButton.disabled = false;
                alert('Si è verificato un errore durante la riproduzione dell\'audio. Prova a premere il pulsante play manualmente.');
            });
        };
        
        responsePlayer.onended = function() {
            console.log('Riproduzione terminata');
            chatPlayButton.innerHTML = '<i class="fas fa-play"></i>';
            chatPlayButton.disabled = false;
        };
    }
}

// Aggiungi questa funzione dopo la funzione initializePushToTalk()
function initializeCustomPlayPauseButton() {
    const audioPlayer = document.getElementById('audio-player');
    const customPlayPauseButton = document.getElementById('custom-play-pause');
    const pushToTalkButton = document.getElementById('push-to-talk');

    function updatePushToTalkButton() {
        if (!audioPlayer.paused) {
            pushToTalkButton.disabled = true;
            pushToTalkButton.classList.add('disabled');
        } else {
            pushToTalkButton.disabled = false;
            pushToTalkButton.classList.remove('disabled');
        }
    }

    customPlayPauseButton.addEventListener('click', function() {
        if (audioPlayer.paused) {
            audioPlayer.play();
            customPlayPauseButton.innerHTML = '<i class="fas fa-pause"></i>';
        } else {
            audioPlayer.pause();
            customPlayPauseButton.innerHTML = '<i class="fas fa-play"></i>';
        }
        updatePushToTalkButton();
    });

    audioPlayer.addEventListener('play', function() {
        customPlayPauseButton.innerHTML = '<i class="fas fa-pause"></i>';
        updatePushToTalkButton();
    });

    audioPlayer.addEventListener('pause', function() {
        customPlayPauseButton.innerHTML = '<i class="fas fa-play"></i>';
        updatePushToTalkButton();
    });

    audioPlayer.addEventListener('ended', updatePushToTalkButton);

    // Inizializza lo stato del pulsante al caricamento della pagina
    updatePushToTalkButton();
}

function initializeChatButton() {
    const openChatModalButton = document.getElementById('open-chat-modal');
    const chatBotModal = document.getElementById('chatBotModal');
    const closeChatModalButton = chatBotModal.querySelector('.close');
    const chatPlayButton = document.getElementById('chat-play');
    const responsePlayer = document.getElementById('response-player');
    const mainAudioPlayer = document.getElementById('audio-player');
    const customPlayPauseButton = document.getElementById('custom-play-pause');

    openChatModalButton.addEventListener('click', function() {
        chatBotModal.style.display = 'block';
        // Metti in pausa il player principale
        mainAudioPlayer.pause();
        customPlayPauseButton.innerHTML = '<i class="fas fa-play"></i>';
    });

    closeChatModalButton.addEventListener('click', function() {
        chatBotModal.style.display = 'none';
        responsePlayer.pause();
        chatPlayButton.innerHTML = '<i class="fas fa-play"></i>';
        // Non disabilitare il pulsante qui, a meno che non ci sia audio
        if (!responsePlayer.src) {
            chatPlayButton.disabled = true;
        }
    });

    window.addEventListener('click', function(event) {
        if (event.target == chatBotModal) {
            chatBotModal.style.display = 'none';
            responsePlayer.pause();
            chatPlayButton.innerHTML = '<i class="fas fa-play"></i>';
            // Non disabilitare il pulsante qui, a meno che non ci sia audio
            if (!responsePlayer.src) {
                chatPlayButton.disabled = true;
            }
        }
    });

    chatPlayButton.addEventListener('click', function() {
        if (responsePlayer.paused) {
            responsePlayer.play();
            chatPlayButton.innerHTML = '<i class="fas fa-pause"></i>';
        } else {
            responsePlayer.pause();
            chatPlayButton.innerHTML = '<i class="fas fa-play"></i>';
        }
    });

    responsePlayer.addEventListener('ended', function() {
        chatPlayButton.innerHTML = '<i class="fas fa-play"></i>';
        chatPlayButton.disabled = false;  // Assicurati che il pulsante rimanga abilitato dopo la fine della riproduzione
    });
}

