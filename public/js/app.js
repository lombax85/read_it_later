document.addEventListener('DOMContentLoaded', function() {
    fetchLinks();
    fetchPodcasts();
    handleResize();
});

let selectedLinks = [];

function fetchLinks() {
    fetch('./api/links')
        .then(response => response.json())
        .then(links => {
            console.log('Links received from backend:', links);
            const linkList = document.getElementById('link-list');
            linkList.innerHTML = '';
            links.forEach(link => {
                const linkElement = createLinkElement(link);
                linkList.appendChild(linkElement);
            });
        })
        .catch(error => console.error('Errore nel recupero dei link:', error));
}

function createLinkElement(link) {
    console.log('Creating element for link:', link);
    const div = document.createElement('div');
    div.className = 'link-item';
    div.innerHTML = `
        <div class="link-header">
            <label class="link-checkbox">
                <input type="checkbox" onchange="toggleLinkSelection(${link.id}, '${link.url}')">
                <span class="checkmark"></span>
            </label>
            <h3>${link.title || 'Titolo non disponibile'}</h3>
        </div>
        <p>${link.category || 'Categoria non disponibile'}</p>
        <a href="${link.url || '#'}" target="_blank">${link.url ? 'Visita il link' : 'URL non disponibile'}</a>
        <div class="link-actions">
            <button onclick="generateOrShowSummary(${link.id}, '${link.url || ''}')">
                ${link.summary ? 'Mostra Riassunto' : 'Genera Riassunto'}
            </button>
            <button onclick="deleteLink(${link.id})">Elimina</button>
        </div>
        <div id="accordion-item-${link.id}" class="accordion-item"></div>
    `;
    return div;
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

function generatePodcast() {
    fetch('./api/generate-podcast', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ links: selectedLinks }),
    })
    .then(response => response.json())
    .then(data => {
        alert('Podcast generato con successo!');
        updatePodcastPlayer(data.podcastUrl);
    })
    .catch(error => console.error('Errore nella generazione del podcast:', error));
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
    audioPlayer.src = podcastSelect.value;
}

function generateOrShowSummary(id, url) {
    if (url) {
        fetch(`./api/summary/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.summary) {
                showSummaryInAccordion(id, data.summary);
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

    // Apri l'accordion dopo un breve ritardo
    setTimeout(() => {
        const content = accordionItem.querySelector('.accordion-content');
        accordionItem.classList.add('active');
        content.style.maxHeight = content.scrollHeight + 'px';
    }, 100);
}

function toggleAccordion(id) {
    const item = document.getElementById(`accordion-item-${id}`);
    const content = item.querySelector('.accordion-content');
    
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
        fetchLinks(); // Aggiorna la lista dei link per riflettere il nuovo stato
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

document.getElementById('addLinkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const url = document.getElementById('url').value;
    const summaryLength = document.getElementById('summaryLength').value;
    const language = document.getElementById('language').value;

    addAndSummarizeLink(url, summaryLength, language);
});

function addAndSummarizeLink(url, summaryLength, language) {
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
            language: language
        }),
    })
    .then(response => response.json())
    .then(data => {
        // Rimuovi l'icona di attesa
        document.body.removeChild(loadingIcon);

        // Chiudi la modale
        document.getElementById('addLinkModal').style.display = 'none';
        document.getElementById('addLinkForm').reset();

        // Aggiorna la pagina per mostrare il nuovo link
        location.reload();
    })
    .catch(error => {
        console.error('Errore nell\'aggiunta del link e generazione del riassunto:', error);
        // Rimuovi l'icona di attesa anche in caso di errore
        document.body.removeChild(loadingIcon);
        alert('Si è verificato un errore durante l\'aggiunta del link. Riprova più tardi.');
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
    
    if (podcasts.length > 0) {
        podcastSection.style.display = 'block';
        podcastSelect.innerHTML = '';
        podcasts.forEach((podcast, index) => {
            const option = document.createElement('option');
            option.value = podcast;
            const date = new Date(podcast.match(/podcast_(\d+)\.mp3/)[1] * 1000);
            option.textContent = `Podcast del ${date.toLocaleDateString()} ${date.toLocaleTimeString()}`;
            podcastSelect.appendChild(option);
        });
        // Seleziona l'ultimo podcast di default
        podcastSelect.value = podcasts[podcasts.length - 1];
        changePodcast();
    } else {
        podcastSection.style.display = 'none';
    }
}

function changePodcast() {
    const podcastSelect = document.getElementById('podcast-select');
    const audioPlayer = document.getElementById('audio-player');
    audioPlayer.src = podcastSelect.value;
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

function generatePodcast(length, language) {
    // Mostra l'icona di attesa
    const loadingIcon = document.createElement('div');
    loadingIcon.id = 'loadingIcon';
    loadingIcon.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generazione podcast in corso...';
    document.body.appendChild(loadingIcon);

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
        // Rimuovi l'icona di attesa
        document.body.removeChild(loadingIcon);

        // Chiudi la modale
        document.getElementById('generatePodcastModal').style.display = 'none';
        document.getElementById('generatePodcastForm').reset();

        // Aggiorna la lista dei podcast
        fetchPodcasts();

        alert('Podcast generato con successo!');
    })
    .catch(error => {
        console.error('Errore nella generazione del podcast:', error);
        // Rimuovi l'icona di attesa anche in caso di errore
        document.body.removeChild(loadingIcon);
        alert('Si è verificato un errore durante la generazione del podcast. Riprova più tardi.');
    });
}

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