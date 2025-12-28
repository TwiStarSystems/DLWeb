// Global state
let currentPageId = null;
let currentBlocks = [];
let isSourceMode = false;
let currentPageCanEdit = true;
let currentPageCanDelete = true;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for markdown preview
    const markdownContent = document.getElementById('markdownContent');
    if (markdownContent) {
        markdownContent.addEventListener('input', updateMarkdownPreview);
    }
});

// New Page
function newPage() {
    if (typeof userPermissions !== 'undefined' && !userPermissions.canCreate) {
        alert('You do not have permission to create pages.');
        return;
    }
    
    currentPageId = null;
    currentPageCanEdit = true;
    currentPageCanDelete = true;
    document.getElementById('pageId').value = '';
    document.getElementById('pageTitle').value = '';
    document.getElementById('pageSlug').value = '';
    document.getElementById('contentType').value = 'html';
    document.getElementById('htmlContent').innerHTML = '';
    document.getElementById('markdownContent').value = '';
    document.getElementById('blockContent').innerHTML = '';
    currentBlocks = [];
    
    switchEditor();
    showEditor();
    updateEditorButtons();
}

// Load Page
function loadPage(id) {
    const formData = new URLSearchParams();
    formData.append('action', 'load');
    formData.append('id', id);
    if (typeof csrfToken !== 'undefined') {
        formData.append('csrf_token', csrfToken);
    }
    
    fetch('/admin/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const page = data.page;
            currentPageId = page.id;
            currentPageCanEdit = page.can_edit !== false;
            currentPageCanDelete = page.can_delete !== false;
            
            document.getElementById('pageId').value = page.id;
            document.getElementById('pageTitle').value = page.title;
            document.getElementById('pageSlug').value = page.slug;
            document.getElementById('contentType').value = page.content_type;
            
            // Load content based on type
            if (page.content_type === 'html') {
                document.getElementById('htmlContent').innerHTML = page.content;
            } else if (page.content_type === 'markdown') {
                document.getElementById('markdownContent').value = page.content;
                updateMarkdownPreview();
            } else if (page.content_type === 'blocks') {
                currentBlocks = JSON.parse(page.content || '[]');
                renderBlocks();
            }
            
            switchEditor();
            showEditor();
            updateEditorButtons();
        } else {
            alert('Error loading page: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Update editor buttons based on permissions
function updateEditorButtons() {
    const saveBtn = document.querySelector('.editor-actions .btn-primary');
    const deleteBtn = document.querySelector('.editor-actions .btn-danger');
    
    // Enable/disable inputs based on edit permission
    const inputs = document.querySelectorAll('#editorPanel input, #editorPanel select, #editorPanel textarea, #htmlContent');
    inputs.forEach(input => {
        if (currentPageCanEdit) {
            input.removeAttribute('disabled');
            if (input.id === 'htmlContent') {
                input.setAttribute('contenteditable', 'true');
            }
        } else {
            input.setAttribute('disabled', 'disabled');
            if (input.id === 'htmlContent') {
                input.setAttribute('contenteditable', 'false');
            }
        }
    });
    
    // Update save button
    if (saveBtn) {
        if (currentPageCanEdit) {
            saveBtn.style.display = '';
            saveBtn.removeAttribute('disabled');
        } else {
            saveBtn.style.display = 'none';
        }
    }
}

// Save Page
function savePage() {
    if (!currentPageCanEdit) {
        alert('You do not have permission to edit this page.');
        return;
    }
    
    const title = document.getElementById('pageTitle').value;
    const slug = document.getElementById('pageSlug').value;
    const contentType = document.getElementById('contentType').value;
    let content = '';
    
    // Get content based on type
    if (contentType === 'html') {
        content = isSourceMode 
            ? document.getElementById('htmlSource').value
            : document.getElementById('htmlContent').innerHTML;
    } else if (contentType === 'markdown') {
        content = document.getElementById('markdownContent').value;
    } else if (contentType === 'blocks') {
        content = JSON.stringify(currentBlocks);
    }
    
    const formData = new URLSearchParams();
    formData.append('action', 'save');
    formData.append('title', title);
    formData.append('slug', slug);
    formData.append('content', content);
    formData.append('content_type', contentType);
    if (currentPageId) {
        formData.append('id', currentPageId);
    }
    if (typeof csrfToken !== 'undefined') {
        formData.append('csrf_token', csrfToken);
    }
    
    fetch('/admin/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Page saved successfully!');
            currentPageId = data.id;
            location.reload();
        } else {
            alert('Error saving page: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Delete Page
function deletePage(id) {
    if (!confirm('Are you sure you want to delete this page?')) {
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('action', 'delete');
    formData.append('id', id);
    if (typeof csrfToken !== 'undefined') {
        formData.append('csrf_token', csrfToken);
    }
    
    fetch('/admin/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Page deleted successfully!');
            location.reload();
        } else {
            alert('Error deleting page: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Switch Editor Type
function switchEditor() {
    const contentType = document.getElementById('contentType').value;
    
    // Hide all editors
    document.getElementById('htmlEditor').style.display = 'none';
    document.getElementById('markdownEditor').style.display = 'none';
    document.getElementById('blockEditor').style.display = 'none';
    
    // Show selected editor
    if (contentType === 'html') {
        document.getElementById('htmlEditor').style.display = 'block';
    } else if (contentType === 'markdown') {
        document.getElementById('markdownEditor').style.display = 'block';
        updateMarkdownPreview();
    } else if (contentType === 'blocks') {
        document.getElementById('blockEditor').style.display = 'block';
    }
}

// HTML Editor Functions
function execCmd(command, value = null) {
    document.execCommand(command, false, value);
    document.getElementById('htmlContent').focus();
}

function showHTMLSource() {
    const content = document.getElementById('htmlContent');
    const source = document.getElementById('htmlSource');
    
    if (isSourceMode) {
        // Switch back to visual mode
        // WARNING: This allows arbitrary HTML input. In production, you should:
        // 1. Add authentication/authorization to the admin panel
        // 2. Use DOMPurify library: content.innerHTML = DOMPurify.sanitize(source.value);
        // 3. Implement Content Security Policy (CSP)
        // 4. Restrict HTML editor to trusted administrators only
        content.innerHTML = source.value;
        content.style.display = 'block';
        source.style.display = 'none';
        isSourceMode = false;
    } else {
        // Switch to source mode
        source.value = content.innerHTML;
        content.style.display = 'none';
        source.style.display = 'block';
        isSourceMode = true;
    }
}

// Markdown Functions
function updateMarkdownPreview() {
    const markdown = document.getElementById('markdownContent').value;
    const preview = document.getElementById('markdownPreview');
    preview.innerHTML = parseMarkdown(markdown);
}

function parseMarkdown(text) {
    // Simple markdown parser with XSS protection
    let html = text;
    
    // Headers (escape the content)
    html = html.replace(/^### (.+)$/gm, function(match, p1) {
        return '<h3>' + escapeHtml(p1) + '</h3>';
    });
    html = html.replace(/^## (.+)$/gm, function(match, p1) {
        return '<h2>' + escapeHtml(p1) + '</h2>';
    });
    html = html.replace(/^# (.+)$/gm, function(match, p1) {
        return '<h1>' + escapeHtml(p1) + '</h1>';
    });
    
    // Bold and italic (escape the content)
    html = html.replace(/\*\*(.+?)\*\*/g, function(match, p1) {
        return '<strong>' + escapeHtml(p1) + '</strong>';
    });
    html = html.replace(/\*(.+?)\*/g, function(match, p1) {
        return '<em>' + escapeHtml(p1) + '</em>';
    });
    
    // Links (escape both text and URL)
    html = html.replace(/\[(.+?)\]\((.+?)\)/g, function(match, text, url) {
        return '<a href="' + escapeHtml(url) + '">' + escapeHtml(text) + '</a>';
    });
    
    // Lists - convert list items to <li> tags (escape content)
    html = html.replace(/^\- (.+)$/gm, function(match, p1) {
        return '<li>' + escapeHtml(p1) + '</li>';
    });
    
    // Wrap consecutive list items in <ul>
    html = html.replace(/(<li>.*?<\/li>\n?)+/g, function(match) {
        return '<ul>' + match + '</ul>';
    });
    
    // Paragraphs - split by double newlines and wrap non-HTML lines
    html = html.split('\n\n').map(para => {
        if (!para.match(/^<[hul]/)) {
            return '<p>' + para + '</p>';
        }
        return para;
    }).join('');
    
    return html;
}

// Block Editor Functions
function addBlock(type) {
    const block = {
        id: Date.now(),
        type: type,
        content: '',
        html: ''
    };
    
    currentBlocks.push(block);
    renderBlocks();
}

function removeBlock(id) {
    currentBlocks = currentBlocks.filter(b => b.id !== id);
    renderBlocks();
}

function moveBlockUp(id) {
    const index = currentBlocks.findIndex(b => b.id === id);
    if (index > 0) {
        [currentBlocks[index - 1], currentBlocks[index]] = [currentBlocks[index], currentBlocks[index - 1]];
        renderBlocks();
    }
}

function moveBlockDown(id) {
    const index = currentBlocks.findIndex(b => b.id === id);
    if (index < currentBlocks.length - 1) {
        [currentBlocks[index], currentBlocks[index + 1]] = [currentBlocks[index + 1], currentBlocks[index]];
        renderBlocks();
    }
}

function updateBlock(id) {
    const block = currentBlocks.find(b => b.id === id);
    if (!block) return;
    
    const input = document.getElementById(`block-input-${id}`);
    block.content = input.value;
    
    // Generate HTML based on type
    if (block.type === 'text') {
        block.html = `<p>${escapeHtml(block.content)}</p>`;
    } else if (block.type === 'heading') {
        block.html = `<h2>${escapeHtml(block.content)}</h2>`;
    } else if (block.type === 'image') {
        block.html = `<img src="${escapeHtml(block.content)}" alt="Image" style="max-width: 100%;">`;
    }
}

function renderBlocks() {
    const container = document.getElementById('blockContent');
    container.innerHTML = '';
    
    currentBlocks.forEach(block => {
        const blockDiv = document.createElement('div');
        blockDiv.className = 'block';
        
        let inputElement = '';
        if (block.type === 'text') {
            inputElement = `<textarea id="block-input-${block.id}" placeholder="Enter text..." onchange="updateBlock(${block.id})">${escapeHtml(block.content)}</textarea>`;
        } else if (block.type === 'heading') {
            inputElement = `<input type="text" id="block-input-${block.id}" placeholder="Enter heading..." value="${escapeHtml(block.content)}" onchange="updateBlock(${block.id})">`;
        } else if (block.type === 'image') {
            inputElement = `<input type="text" id="block-input-${block.id}" placeholder="Enter image URL..." value="${escapeHtml(block.content)}" onchange="updateBlock(${block.id})">`;
        }
        
        blockDiv.innerHTML = `
            <div class="block-controls">
                <button onclick="moveBlockUp(${block.id})">↑</button>
                <button onclick="moveBlockDown(${block.id})">↓</button>
                <button onclick="removeBlock(${block.id})">×</button>
            </div>
            <strong>${block.type.toUpperCase()}</strong>
            ${inputElement}
        `;
        
        container.appendChild(blockDiv);
    });
}

// Helper Functions
function showEditor() {
    document.getElementById('editorPanel').style.display = 'block';
    document.getElementById('welcomeMessage').style.display = 'none';
}

function cancelEdit() {
    document.getElementById('editorPanel').style.display = 'none';
    document.getElementById('welcomeMessage').style.display = 'block';
}

function previewPage() {
    const slug = document.getElementById('pageSlug').value;
    if (slug) {
        window.open('/?page=' + slug, '_blank');
    } else {
        alert('Please enter a slug first');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
