/* script.js */
document.addEventListener('DOMContentLoaded', () => {
    const navContainer = document.getElementById('nav-container');
    const endpointsContainer = document.getElementById('endpoints-container');
    const themeToggleBtn = document.getElementById('theme-toggle');
    
    window.toggleExpand = function(btn) {
        const wrapper = btn.closest('.code-block-wrapper').querySelector('.code-content-wrapper');
        if (wrapper.classList.contains('expanded')) {
            wrapper.classList.remove('expanded');
            btn.textContent = 'Show More';
            const header = btn.closest('.code-block-wrapper');
            header.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            wrapper.classList.add('expanded');
            btn.textContent = 'Show Less';
        }
    };

    // Theme Toggle Logic
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);

    themeToggleBtn.addEventListener('click', () => {
        const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    });

    // ScrollSpy Observer (Tracking the 'flag' on the sidebar)
    const navObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id;
                document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                const activeLink = document.querySelector(`.nav-link[href="#${id}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                    // Ensure the sidebar scrolls if it's out of view
                    activeLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    }, {
        rootMargin: '-10% 0px -80% 0px', // Trigger near the top of the viewport
        threshold: 0
    });

    // Fetch Postman JSON
    fetch('FoodLab.postman_collection.json')
        .then(response => {
            if (!response.ok) throw new Error('Could not load collection file');
            return response.json();
        })
        .then(data => {
            document.title = `${data.info.name} - API Documentation`;
            const heroTitle = document.getElementById('hero-title');
            if (heroTitle) heroTitle.textContent = `${data.info.name} API Reference`;
            
            parseCollection(data.item);
        })
        .catch(err => {
            console.error('Error fetching collection:', err);
            const toast = document.getElementById('error-toast');
            toast.classList.remove('hidden');
            toast.textContent = 'Failed to load FoodLab.postman_collection.json. Please ensure it is served via a local web server (e.g. VSCode Live Server, http-server, or npx serve). CORS blocks fetching local files directly in the browser.';
            setTimeout(() => toast.classList.add('hidden'), 10000);
        });

    function parseCollection(items, folderName = '') {
        items.forEach(item => {
            if (item.item) {
                // It's a folder
                const folderTitle = item.name;
                
                // Add Folder to Nav
                const navFolder = document.createElement('div');
                navFolder.className = 'nav-folder';
                navFolder.textContent = folderTitle;
                navContainer.appendChild(navFolder);
                
                parseCollection(item.item, folderTitle);
            } else if (item.request) {
                // It's a request
                renderEndpoint(item, folderName);
            }
        });
    }

    function renderEndpoint(item, folderName) {
        const reqName = item.name;
        const req = item.request;
        const method = req.method;
        const urlStr = typeof req.url === 'string' ? req.url : req.url?.raw || '';
        // Create unique ID for scrolling linking
        const reqId = `endpoint-${folderName.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${reqName.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;

        // Create Navigation Link
        const navLink = document.createElement('a');
        
        // Ensure links work across pages
        const isIndexPage = window.location.pathname.endsWith('/') || window.location.pathname.endsWith('index.html');
        navLink.href = isIndexPage ? `#${reqId}` : `index.html#${reqId}`;
        navLink.className = 'nav-link';
        
        // Add smooth scrolling behavior through JS only if we are on the index page
        if (isIndexPage) {
            navLink.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.getElementById(reqId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }
        navLink.innerHTML = `<span style="font-size: 0.75rem; font-weight: bold; margin-right: 6px; color: var(--color-${method.toLowerCase()})">${method}</span> ${reqName}`;
        navContainer.appendChild(navLink);

        // Build Endpoint Section HTML (Only if we are on the page that renders endpoints)
        if (!endpointsContainer) return;

        const section = document.createElement('section');
        section.id = reqId;
        section.className = 'endpoint-section';

        // 1. Info Column
        let infoHTML = `
            <div class="endpoint-info">
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">${folderName}</div>
                <h3 class="endpoint-title">${reqName}</h3>
                <div style="display: flex; align-items: center; margin-bottom: 2rem;">
                    <span class="method-badge ${method}">${method}</span>
                    <span class="endpoint-url">${escapeHTML(urlStr)}</span>
                </div>
        `;

        if (req.header && req.header.length > 0) {
            infoHTML += `<h4>Headers</h4><table class="param-table"><tbody>`;
            req.header.forEach(h => {
                infoHTML += `<tr><td>${escapeHTML(h.key)}</td><td>${escapeHTML(typeof h.value === 'string' ? h.value : String(h.value))}</td></tr>`;
            });
            infoHTML += `</tbody></table>`;
        }
        
        let reqBodyJSON = null;
        let reqBodyForm = [];
        if (req.body) {
            if (req.body.mode === 'raw') {
               reqBodyJSON = req.body.raw;
            } else if (req.body.mode === 'formdata') {
               reqBodyForm = req.body.formdata || [];
               infoHTML += `<h4>Form Data</h4><table class="param-table"><tbody>`;
               reqBodyForm.forEach(fd => {
                   infoHTML += `<tr><td>${escapeHTML(fd.key)}</td><td>${escapeHTML(typeof fd.value === 'string' ? fd.value : String(fd.value))} <em>(${fd.type || 'text'})</em></td></tr>`;
               });
               infoHTML += `</tbody></table>`;
            }
        }

        infoHTML += `</div>`; // Close info-column

        // 2. Code/Example Column
        let codeHTML = `<div class="endpoint-code">`;
        
        function makeCodeBlock(title, content) {
            if (!content) return '';
            const contentStr = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
            const lineCount = contentStr.split('\n').length;
            const needsExpand = lineCount > 15;
            let html = `
                <div class="code-block-wrapper">
                    <div class="code-header">${escapeHTML(title)}</div>
                    <div class="code-content-wrapper ${needsExpand ? 'expandable-code' : ''}">
                        <pre class="code-content">${escapeHTML(contentStr)}</pre>
                        ${needsExpand ? '<div class="code-gradient"></div>' : ''}
                    </div>
                    ${needsExpand ? `
                    <div class="expand-btn-container">
                        <button class="expand-btn" onclick="toggleExpand(this)">Show More</button>
                    </div>
                    ` : ''}
                </div>
            `;
            return html;
        }
        
        let hasCodeBlock = false;

        // Example Request Body Code Block
        if (reqBodyJSON) {
            let prettyJSON = reqBodyJSON;
            try { prettyJSON = JSON.stringify(JSON.parse(reqBodyJSON), null, 2); } catch(e){}
            codeHTML += makeCodeBlock("Example Request Body (JSON)", prettyJSON);
            hasCodeBlock = true;
        }

        // Example Response (Take the first response if available)
        if (item.response && item.response.length > 0) {
            let resBody = item.response[0].body;
            let resStatus = item.response[0].name || item.response[0].status || '';
            let parsedRes = resBody;
            try { 
                if (resBody) parsedRes = JSON.stringify(JSON.parse(resBody), null, 2); 
            } catch(e){}

            if (parsedRes) {
                codeHTML += makeCodeBlock(`Response Example ${resStatus ? '- ' + resStatus : ''}`, parsedRes);
                hasCodeBlock = true;
            }
        }

        if (!hasCodeBlock) {
             codeHTML += `
                <div style="padding: 2rem; border-radius: var(--radius-lg); border: 1px dashed var(--border-color); color: var(--text-muted); text-align: center; font-size: 0.85rem; margin-top: 2rem;">
                    <em>No Code Examples or Responses saved in Postman Collection.</em>
                </div>
             `;
        }

        codeHTML += `</div>`; // Close code-column

        section.innerHTML = infoHTML + codeHTML;
        endpointsContainer.appendChild(section);
        
        // Observe this section for scroll spy
        navObserver.observe(section);
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }
});
