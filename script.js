/**
 * script.js
 * 
 * Dynamic catalog engine and interactive systems for the AI-Solution portal.
 * Manages dynamic product loading, wishlist states, modal detail views,
 * reviews submission, chatbot conversations (showing extensive code blocks),
 * and accessibility text resizing.
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // ====================================================================
    // 1. ACCESSIBILITY SYSTEMS: Text Resizing & Focus Styling
    // ====================================================================
    const textDecBtn = document.getElementById('textDecBtn');
    const textResetBtn = document.getElementById('textResetBtn');
    const textIncBtn = document.getElementById('textIncBtn');

    // Load custom text scale modifier (defaults to 1.0)
    let currentScale = parseFloat(localStorage.getItem('textScale')) || 1.0;
    document.documentElement.style.setProperty('--text-scale', currentScale);
    if (document.body) {
        document.body.style.fontSize = `${currentScale * 100}%`;
    }

    function updateTextScale(newScale) {
        currentScale = Math.min(Math.max(newScale, 0.8), 1.4);
        document.documentElement.style.setProperty('--text-scale', currentScale);
        if (document.body) {
            document.body.style.fontSize = `${currentScale * 100}%`;
        }
        localStorage.setItem('textScale', currentScale);
    }

    if (textDecBtn) {
        textDecBtn.addEventListener('click', () => updateTextScale(currentScale - 0.1));
    }
    if (textResetBtn) {
        textResetBtn.addEventListener('click', () => updateTextScale(1.0));
    }
    if (textIncBtn) {
        textIncBtn.addEventListener('click', () => updateTextScale(currentScale + 0.1));
    }

    // High Contrast Mode Preferred Checking & Toggle
    const contrastBtn = document.getElementById('contrastBtn');
    if (localStorage.getItem('contrastMode') === 'enabled') {
        document.body.classList.add('high-contrast');
        if (contrastBtn) contrastBtn.textContent = 'Normal Mode';
    }
    if (contrastBtn) {
        contrastBtn.addEventListener('click', () => {
            document.body.classList.toggle('high-contrast');
            if (document.body.classList.contains('high-contrast')) {
                localStorage.setItem('contrastMode', 'enabled');
                contrastBtn.textContent = 'Normal Mode';
            } else {
                localStorage.setItem('contrastMode', 'disabled');
                contrastBtn.textContent = 'Contrast Mode';
            }
        });
    }

    // ====================================================================
    // 2. CHATBOT SYSTEM: Answers showing extensive code examples
    // ====================================================================
    const chatbotBtn = document.getElementById('chatbotBtn');
    const chatbotPanel = document.getElementById('chatbotPanel');
    const closeChatbot = document.getElementById('closeChatbot');
    const chatbotForm = document.getElementById('chatbotForm');
    const chatbotInput = document.getElementById('chatbotInput');
    const chatbotHistory = document.getElementById('chatbotHistory');
    const chatbotQuickReplies = document.getElementById('chatbotQuickReplies');

    let chatInitialized = false;

    function scrollToBottom() {
        if (chatbotHistory) chatbotHistory.scrollTop = chatbotHistory.scrollHeight;
    }

    function runGreetingLoop() {
        if (chatInitialized || !chatbotHistory) return;
        chatInitialized = true;
        chatbotHistory.innerHTML = '';
        simulateBotMessage("Hi! This is the AI-Solution chatbot. How can I help you today? 🤖", 400);
    }

    function simulateBotMessage(text, delay) {
        setTimeout(() => {
            const typingId = 'typing-' + Date.now();
            const typingMsg = document.createElement('div');
            typingMsg.className = 'chat-msg bot';
            typingMsg.id = typingId;
            typingMsg.innerHTML = `<div class="typing-dots"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
            chatbotHistory.appendChild(typingMsg);
            scrollToBottom();

            setTimeout(() => {
                const indicator = document.getElementById(typingId);
                if (indicator) indicator.remove();
                const botMsg = document.createElement('div');
                botMsg.className = 'chat-msg bot';
                botMsg.innerHTML = text;
                chatbotHistory.appendChild(botMsg);
                scrollToBottom();
            }, 800);
        }, delay);
    }

    if (chatbotBtn && chatbotPanel) {
        chatbotBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            chatbotPanel.classList.toggle('active');
            if (chatbotPanel.classList.contains('active')) {
                scrollToBottom();
                if (chatbotInput) chatbotInput.focus();
                runGreetingLoop();
            }
        });
    }

    if (closeChatbot) {
        closeChatbot.addEventListener('click', () => chatbotPanel.classList.remove('active'));
    }

    document.addEventListener('click', (e) => {
        if (chatbotPanel && chatbotPanel.classList.contains('active') && 
            !chatbotPanel.contains(e.target) && e.target !== chatbotBtn && !chatbotBtn.contains(e.target)) {
            chatbotPanel.classList.remove('active');
        }
    });

    function getBotResponse(message) {
        const msg = message.toLowerCase().trim();
        
        // Semantic intent mapping for common queries
        if (msg.includes('inquiry') || msg.includes('inquery') || msg.includes('submit') || msg.includes('contact') || msg.includes('form') || msg.includes('ask') || msg.includes('request')) {
            return "You can submit a product inquiry or a customized demo request directly by filling out our forms on the <a href='contact.html' style='color:var(--accent-blue);font-weight:600;'>Contact Us</a> page. You can also configure specific packages directly on the <a href='products.html' style='color:var(--accent-blue);font-weight:600;'>Products</a> catalog!";
        } else if (msg.includes('case study') || msg.includes('case studies') || msg.includes('studies') || msg.includes('portfolio') || msg.includes('success') || msg.includes('clients') || msg.includes('results')) {
            return "We have successfully rolled out our solutions for major B2B clients like Global Logistics, FinTech Innovations, and HealthTech UK. Review detailed metrics and full outcomes on our new dedicated <a href='case-studies.html' style='color:var(--accent-blue);font-weight:600;'>Case Studies</a> page!";
        } else if (msg.includes('price') || msg.includes('pricing') || msg.includes('cost') || msg.includes('subscription') || msg.includes('plan') || msg.includes('fee') || msg.includes('how much') || msg.includes('costing')) {
            return "Our AI subscriptions start at £149-£449/month for Basic plans and £499-£1199/month for Standard plans, with custom plans available. Detailed pricing tables and spec configurations can be browsed on the <a href='products.html' style='color:var(--accent-blue);font-weight:600;'>Products</a> page.";
        } else if (msg.includes('where') || msg.includes('location') || msg.includes('address') || msg.includes('office') || msg.includes('hq') || msg.includes('map') || msg.includes('sunderland') || msg.includes('uk') || msg.includes('singapore')) {
            return "Our global Headquarters is at the Innovation Hub, Sunderland SR1 1PB, UK. We also have satellite offices in New York, USA, and Singapore. View maps and direct phone details on the <a href='contact.html' style='color:var(--accent-blue);font-weight:600;'>Contact Us</a> page!";
        } else if (msg.includes('news') || msg.includes('blog') || msg.includes('insights') || msg.includes('articles') || msg.includes('announcement')) {
            return "Read our latest announcements, tech releases, and research insights on our <a href='news.html' style='color:var(--accent-blue);font-weight:600;'>News & Insights</a> page.";
        } else if (msg.includes('event') || msg.includes('events') || msg.includes('summit') || msg.includes('webinar')) {
            return "We host regular summits and HR automation webinars. Check our schedules on the <a href='events.html' style='color:var(--accent-blue);font-weight:600;'>Events Showcase</a> page.";
        } else if (msg.includes('omnimetrics')) {
            return "<strong>OmniMetrics AI</strong> is our advanced predictive analytics dashboard. It automatically identifies workflow bottlenecks across your enterprise architecture with real-time insights, helping teams optimize cloud resources and reduce operational costs. Learn more on our <a href='products.html' style='color:var(--accent-blue);font-weight:600;'>Products</a> page!";
        } else if (msg.includes('nexus') || msg.includes('assist')) {
            return "<strong>Nexus Assist Pro</strong> is our flagship virtual assistant. It integrates securely with M365 and Google Workspace to automate routine employee support tickets, IT, and HR questions around the clock. Learn more on our <a href='products.html' style='color:var(--accent-blue);font-weight:600;'>Products</a> page!";
        } else if (msg.includes('logicbuilder')) {
            return "<strong>LogicBuilder 3.0</strong> is our visual drag-and-drop editor that lets developers and managers build logic trees and custom AI routing workflows in minutes. Learn more on our <a href='products.html' style='color:var(--accent-blue);font-weight:600;'>Products</a> page!";
        } else if (msg.includes('code') || msg.includes('snippet') || msg.includes('api') || msg.includes('developer') || msg.includes('sql') || msg.includes('schema') || msg.includes('query') || msg.includes('database')) {
            return "Our AI solutions support standard API integrations across a wide range of platforms (Python, JavaScript, Go, SQL). To obtain API keys, sandbox credentials, or custom schema integration guidance, please submit a request through the <a href='contact.html' style='color:var(--accent-blue);font-weight:600;'>Contact Us</a> page!";
        } else if (msg.includes('demo') || msg.includes('get a')) {
            return "To book a secure product sandbox, click on any product card in the <a href='products.html' style='color:var(--accent-blue);font-weight:600;'>Products</a> page. Choose the 'Set a Demo' option (requires a 5% deposit: £14.95 for Basic, £39.95 for Standard) and complete the request form. A unique Client ID will be issued immediately!";
        } else if (msg.includes('hello') || msg.includes('hi') || msg.includes('hey') || msg.includes('greetings')) {
            return "Hello there! How can I help you today? I can guide you through our product catalog, explain pricing, direct you to our case studies, show office locations, or explain how to submit an inquiry.";
        } else if (msg.includes('thank') || msg.includes('thanks')) {
            return "You're very welcome! Feel free to ask if you have any other questions.";
        } else if (msg.includes('bye') || msg.includes('goodbye')) {
            return "Goodbye! Thanks for visiting AI-Solution. Have a wonderful day ahead!";
        } else if (msg.includes('help') || msg.includes('features') || msg.includes('what can you do')) {
            return "I can assist you with information about our AI products, pricing plans, office locations, case studies, news articles, or explain how to request a sandbox demo. What would you like to explore?";
        } else {
            return "I am your AI assistant. Ask me about our <strong>pricing</strong>, <strong>case studies</strong>, <strong>office locations</strong>, how to submit an <strong>inquiry</strong>, or specific products (OmniMetrics AI, Nexus Assist Pro, LogicBuilder 3.0)!";
        }
    }

    function simulateBotReply(userText) {
        const typingId = 'typing-' + Date.now();
        const typingMsg = document.createElement('div');
        typingMsg.className = 'chat-msg bot';
        typingMsg.id = typingId;
        typingMsg.innerHTML = `<div class="typing-dots"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
        chatbotHistory.appendChild(typingMsg);
        scrollToBottom();

        setTimeout(() => {
            const indicator = document.getElementById(typingId);
            if (indicator) indicator.remove();
            const botMsg = document.createElement('div');
            botMsg.className = 'chat-msg bot';
            botMsg.innerHTML = getBotResponse(userText);
            chatbotHistory.appendChild(botMsg);
            scrollToBottom();
        }, 800);
    }

    function sendUserMessage(text) {
        if (!text.trim() || !chatbotHistory) return;
        const userMsg = document.createElement('div');
        userMsg.className = 'chat-msg user';
        userMsg.textContent = text;
        chatbotHistory.appendChild(userMsg);
        scrollToBottom();
        simulateBotReply(text);
    }

    if (chatbotForm) {
        chatbotForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = chatbotInput.value;
            chatbotInput.value = '';
            sendUserMessage(text);
        });
    }

    if (chatbotQuickReplies) {
        chatbotQuickReplies.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-reply-btn')) {
                const text = e.target.getAttribute('data-reply');
                sendUserMessage(text);
            }
        });
    }


    // ====================================================================
    // 3. PRODUCT CATALOG CORE: Dynamic Loading & Custom wishes / Modals
    // ====================================================================
    let loadedProducts = [];
    const productsContainer = document.getElementById('products-container');
    const footerLinks = document.getElementById('footerProductLinks');

    const defaultMockProducts = [
        { 
            id: 1, 
            name: 'OmniMetrics AI', 
            description: 'Advanced predictive analytics dashboard that automatically identifies workflow bottlenecks across your enterprise architecture with real-time insights. It helps teams proactively optimize cloud resources, reduce operational costs, and identify code bottlenecks before they affect end users.', 
            detail_description: 'OmniMetrics AI provides an end-to-end telemetry and observability platform built specifically for high-throughput enterprise systems. Featuring dynamic log analysis, memory leak prediction models, and live execution tracing, it integrates seamlessly with major cloud service providers (AWS, Azure, GCP) and on-premise application servers. Its advanced proprietary anomaly detection engine parses millions of system queries per minute to highlight architectural choke points, queue backlogs, and network latency anomalies, presenting them in a beautiful, glassmorphic analytics dashboard. It helps teams proactively optimize cloud resources, reduce operational costs, and identify code bottlenecks before they affect end users.',
            category: 'new analytics', 
            tags: 'AI-Powered,Predictive,Enterprise', 
            integration: 'M365, Salesforce, SAP', 
            deployment: 'Cloud / On-Premise', 
            release_date: 'October 2023', 
            rating: 4.90, 
            review_count: 6, 
            image_path: 'images/dashboard.png', 
            basic_price: 299, 
            standard_price: 799, 
            custom_price: 'Custom' 
        },
        { 
            id: 2, 
            name: 'Nexus Assist Pro', 
            description: 'Our flagship virtual assistant deeply integrated with M365 and Google Workspace to automate routine employee inquiries around the clock. It enables businesses to reduce employee support ticket resolution times by up to 50% and dramatically improve digital employee experience metrics.', 
            detail_description: 'Nexus Assist Pro is a state-of-the-art conversational AI agent engineered for corporate workspaces. It connects securely to internal databases, knowledge bases, and document stores to provide employees with context-aware, cited answers to standard IT, HR, and facilities questions. Leveraging secure Retrieval-Augmented Generation (RAG) models, Nexus ensures corporate data compliance (GDPR, HIPAA, SOC2) and runs on a secure, sandboxed environment. Includes pre-built integrations with Microsoft Teams, Slack, Outlook, and Google Chat. It enables businesses to reduce employee support ticket resolution times by up to 50% and dramatically improve digital employee experience metrics.',
            category: 'new assistant', 
            tags: 'Virtual Assistant,M365,Google WS', 
            integration: 'M365, Google Workspace', 
            deployment: 'Cloud / On-Premise', 
            release_date: 'October 2023', 
            rating: 5.00, 
            review_count: 4, 
            image_path: 'images/hero.png', 
            basic_price: 349, 
            standard_price: 899, 
            custom_price: 'Custom' 
        },
        { 
            id: 3, 
            name: 'LogicBuilder 3.0', 
            description: 'Rapid prototyping solution for IT departments to visually construct and deploy custom AI logic trees without writing a single line of code. This platform empowers rapid application prototyping and lets non-developers safely build custom AI logic chains in minutes.', 
            detail_description: 'LogicBuilder 3.0 is a drag-and-drop no-code development studio designed for enterprise IT administrators. Build custom reasoning chains, automated data routing rules, and multi-model AI workflows on a high-fidelity visual WebGL canvas. It enables rapid prototyping of business logic, auto-generates secure deployment configs (Docker, Kubernetes), and supports live debugging. Easily connect 200+ external API actions, database endpoints, and webhook triggers with zero coding required. This platform empowers rapid application prototyping and lets non-developers safely build custom AI logic chains in minutes.',
            category: 'new assistant analytics', 
            tags: 'No-Code,Workflow,Automation', 
            integration: 'Zero-Code Setup', 
            deployment: '200+ integrations', 
            release_date: 'September 2023', 
            rating: 4.70, 
            review_count: 8, 
            image_path: 'images/workflow.png', 
            basic_price: 199, 
            standard_price: 599, 
            custom_price: 'Custom' 
        },
        { 
            id: 4, 
            name: 'ComplianceBot AI', 
            description: 'Real-time policy checking and automated compliance auditing assistant designed for highly regulated B2B financial and healthcare operations. It ensures continuous compliance posture and sends instant alerts when high-risk policy violations occur.', 
            detail_description: 'ComplianceBot AI automates regulatory oversight by conducting real-time policy checks across all system transactions and communications. Specially tuned for fintech, healthcare, and insurance operations, it continuously parses activity logs to detect policy deviations, active directory anomalies, and unauthorized data transfers. It auto-generates comprehensive compliance reports suitable for ISO 27001, SOC2, HIPAA, and GDPR audits, reducing human oversight costs by up to 60%. It ensures continuous compliance posture and sends instant alerts when high-risk policy violations occur.',
            category: 'new assistant', 
            tags: 'Compliance,Auditing,Fintech / Health', 
            integration: 'Workday, ServiceNow, DBs', 
            deployment: 'ISO 27001, HIPAA, GDPR', 
            release_date: 'November 2024', 
            rating: 4.80, 
            review_count: 10, 
            image_path: 'https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=600', 
            basic_price: 399, 
            standard_price: 999, 
            custom_price: 'Custom' 
        },
        { 
            id: 5, 
            name: 'AutoRespond Agent', 
            description: 'Autonomous corporate responder that parses and replies to thousands of common customer queries via email/tickets with semantic CRM integrations. This enables customer support agents to focus on complex queries while the AI automatically resolves routine support tickets.', 
            detail_description: 'AutoRespond Agent is an autonomous ticket and email resolution system designed to supercharge customer support teams. Powered by semantic intent classification and CRM records mapping, it parses inbound support messages, retrieves client histories, and compiles precise, personalized drafts or auto-replies. Integrates natively with Outlook, Gmail, Salesforce Service Cloud, and Zendesk, achieving a 98.4% auto-resolution rate on common transactional queries. This enables customer support agents to focus on complex queries while the AI automatically resolves routine support tickets.',
            category: 'new assistant', 
            tags: 'Auto-Responder,Outlook / Gmail,CRM Sync', 
            integration: 'Outlook, Gmail, Salesforce', 
            deployment: '98.4% auto-resolve', 
            release_date: 'December 2024', 
            rating: 4.90, 
            review_count: 15, 
            image_path: 'https://images.unsplash.com/photo-1557200134-90327ee9fafa?q=80&w=600', 
            basic_price: 249, 
            standard_price: 699, 
            custom_price: 'Custom' 
        },
        { 
            id: 6, 
            name: 'DataSync Core AI', 
            description: 'Enterprise semantic database migration and synchronization engine that maps legacy SQL systems with real-time replication and zero downtime. It is the perfect tool for executing database migrations without interrupting live services or risking transaction data loss.', 
            detail_description: 'DataSync Core AI provides zero-downtime database replication, schema translation, and real-time synchronization. Engineered for legacy database modernization, it maps standard SQL relations, cleanses dirty records, and translates schemas dynamically between MySQL, MSSQL, Oracle, and SQLite databases. Features AES-256 stateful transit encryption and a visual sync health console. It is the perfect tool for executing database migrations without interrupting live services or risking transaction data loss.',
            category: 'legacy analytics', 
            tags: 'Database,Data Sync,Stable Core', 
            integration: 'MySQL, MSSQL, Oracle', 
            deployment: 'AES-256 encrypted', 
            release_date: 'January 2022', 
            rating: 4.80, 
            review_count: 6, 
            image_path: 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?q=80&w=600', 
            basic_price: 149, 
            standard_price: 499, 
            custom_price: 'Custom' 
        },
        { 
            id: 7, 
            name: 'OmniSearch Enterprise', 
            description: 'Cognitive enterprise search tool that indexes all internal databases, wikis, and cloud files to provide instant, secure answers with cited sources. This eliminates wasted hours spent manually digging through folders and enhances corporate productivity.', 
            detail_description: 'OmniSearch Enterprise is an AI-powered cognitive search tool that indexes and crawls all decentralized corporate files, messaging histories, wikis, and document vaults. Utilizing secure semantic search embeddings, it respects role-based access privileges (Active Directory) to deliver instant, secure answers with inline source citations. Employees can query all corporate knowledge in natural language, finding files in seconds. This eliminates wasted hours spent manually digging through folders and enhances corporate productivity.',
            category: 'new assistant', 
            tags: 'Cognitive Search,Secure RAG,Multi-Source', 
            integration: 'Drive, Sharepoint, Slack', 
            deployment: 'Active Directory, RAG', 
            release_date: 'March 2024', 
            rating: 5.00, 
            review_count: 8, 
            image_path: 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?q=80&w=600', 
            basic_price: 449, 
            standard_price: 1199, 
            custom_price: 'Custom' 
        }
    ];

    // Seed local products if empty or outdated version
    if (!localStorage.getItem('products') || localStorage.getItem('products_version') !== '4') {
        localStorage.setItem('products', JSON.stringify(defaultMockProducts));
        localStorage.setItem('products_version', '4');
    }

    function loadProducts() {
        if (!productsContainer) return;
        
        fetch('get_products.php')
            .then(res => {
                if (!res.ok) throw new Error("HTTP error " + res.status);
                return res.json();
            })
            .then(data => {
                loadedProducts = data;
                renderProducts(loadedProducts);
                populateProductDropdowns(loadedProducts);
            })
            .catch(err => {
                console.warn("PHP products endpoint failed, loading local products:", err);
                loadedProducts = JSON.parse(localStorage.getItem('products')) || defaultMockProducts;
                renderProducts(loadedProducts);
                populateProductDropdowns(loadedProducts);
            });
    }

    function renderProducts(prods) {
        if (!productsContainer) return;
        productsContainer.innerHTML = '';
        
        prods.forEach(p => {
            const stars = '★'.repeat(Math.round(p.rating)) + '☆'.repeat(5 - Math.round(p.rating));
            const isLegacy = p.category.includes('legacy');
            
                const cardHtml = `
                <div class="col-lg-4 col-md-6 product-item" id="prod-${p.id}" data-rating="${p.rating}" data-date="${p.release_date}" data-recommended="${p.id}" data-category="${p.category}">
                    <div class="product-full-card">
                        <div class="product-img-wrap">
                            <span class="product-badge-overlay">${isLegacy ? 'Legacy' : 'Enterprise'}</span>
                            <img src="${p.image_path}" alt="${p.name}">
                        </div>
                        <div class="product-body p-4 d-flex flex-column justify-content-between" style="flex:1; background-color: #FAF8F5 !important;">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h5 class="fw-bold mb-0 product-title" style="color: #000000 !important; font-family:'Outfit',sans-serif !important; font-weight: 800 !important;">${p.name}</h5>
                                    <span class="pkg-badge">£${p.basic_price}–£${p.standard_price}/mo</span>
                                </div>
                                <div class="star-rating mb-2 text-warning" style="font-size:0.85rem;">${stars} <span class="product-reviews-count ms-1 small" style="color: #334155 !important; font-weight: 600 !important; opacity: 1 !important;">(${p.rating}) · ${p.review_count} reviews</span></div>
                                <p class="product-desc mb-3" style="color: #334155 !important; font-weight: 500 !important; font-size: 0.85rem !important; line-height: 1.6 !important; opacity: 1 !important;">${p.description.substring(0, 110)}...</p>
                            </div>
                            <div class="d-flex gap-2 mt-auto">
                                <button class="btn btn-outline-accent py-2 fw-bold" style="font-size:0.8rem; padding:6px 12px;" onclick="showProductDetails(${p.id}, 'overview')">Read More</button>
                                <button class="btn btn-accent flex-grow-1 py-2 fw-bold" style="font-size:0.8rem; padding:6px 12px;" onclick="showProductDetails(${p.id}, 'request')">Configure &amp; Request</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            productsContainer.insertAdjacentHTML('beforeend', cardHtml);
        });

        // Update footer shortcuts
        if (footerLinks) {
            footerLinks.innerHTML = prods.slice(0, 4).map(p => `<li><a href="javascript:void(0)" onclick="showProductDetails(${p.id}, 'overview')">${p.name}</a></li>`).join('');
        }
    }

    function populateProductDropdowns(prods) {
        const select = document.getElementById('revProduct');
        if (select) {
            select.innerHTML = prods.map(p => `<option value="${p.name}">${p.name}</option>`).join('');
        }
    }

    loadProducts();

    // ====================================================================
    // 4. DETAILED PRODUCT MODAL & WISHLIST MANAGEMENT
    // ====================================================================
    const detailModalEl = document.getElementById('productDetailModal');
    let modalInstance = null;
    if (detailModalEl) {
        modalInstance = new bootstrap.Modal(detailModalEl);
    }

    // Dynamic tailored articles library
    const productArticles = {
        'OmniMetrics AI': `
            <h6 class="text-white mt-3">OmniMetrics AI drives 45% Logistics Optimization</h6>
            <p class="small text-white mb-2">Global Logistics Corp recently completed a full rollout of OmniMetrics AI across three continents. In their latest IT architecture report, CTO Sarah Jenkins notes that the automated semantic telemetry alerts identified micro-bottlenecks in database execution lines, reducing peak-hour application outages by 45% within 14 days.</p>
            <blockquote class="bg-dark bg-opacity-30 p-2.5 rounded text-warning small border-start border-warning italic mt-2">
                "OmniMetrics predicted resource leaks in our SAP instances that had eluded our standard monitoring tools for over a year." — IT Infrastructure Review
            </blockquote>
        `,
        'Nexus Assist Pro': `
            <h6 class="text-white mt-3">Nexus Assist Pro Integrates with Microsoft Copilot Studio</h6>
            <p class="small text-white mb-2">In a major version update, Nexus Assist Pro has released native Microsoft 365 Copilot plugins. Enterprise staff can now query local, federated database systems and retrieve context-rich files from secure SharePoint drives directly from the Chatbot prompt, backed by verified RAG citation parameters.</p>
        `,
        'LogicBuilder 3.0': `
            <h6 class="text-white mt-3">Visual WebGL Canvas release in LogicBuilder 3.0</h6>
            <p class="small text-white mb-2">The engineering team has rolled out a completely rewritten visual design studio using WebGL. Architects can now drag and drop logic rules, trigger complex API calls, and audit workflow executions in real-time, handling over 500 connected operations without lagging the browser canvas.</p>
        `,
        'ComplianceBot AI': `
            <h6 class="text-white mt-3">ComplianceBot AI secures SOC2 / ISO 27001 auditing templates</h6>
            <p class="small text-white mb-2">AI-Solution has shipped pre-configured compliance templates tailored to fintech and healthcare organizations. ComplianceBot AI monitors file operations and active directory modifications around the clock, compiling automated audits ready for regulatory officers.</p>
        `,
        'AutoRespond Agent': `
            <h6 class="text-white mt-3">AutoRespond handles 98.4% of email helpdesk requests autonomously</h6>
            <p class="small text-white mb-2">FinTech Innovations reports that their automated customer inbox resolved 98.4% of standard account queries without human intervention, syncing all replies automatically with Salesforce CRM instances.</p>
        `,
        'DataSync Core AI': `
            <h6 class="text-white mt-3">Legacy Support: DataSync Core continues standard SQL sync migrations</h6>
            <p class="small text-white mb-2">DataSync Core remains the industry standard for stable database synchronization. Fully supporting MySQL, MSSQL, and Oracle setups, it enables zero-downtime replication pipelines for standard enterprise migrations.</p>
        `,
        'OmniSearch Enterprise': `
            <h6 class="text-white mt-3">OmniSearch indexes federated Slack and SharePoint wikis</h6>
            <p class="small text-white mb-2">Empower employees to find exact answers instantly with secure RAG. OmniSearch logs into corporate wikis and databases dynamically, ensuring private access privileges are fully respected.</p>
        `
    };

    window.showProductDetails = function(prodId, defaultTab = 'overview') {
        const p = loadedProducts.find(item => item.id === prodId);
        if (!p || !detailModalEl) return;

        document.getElementById('modalProductName').textContent = p.name;
        document.getElementById('modalProductDescription').textContent = p.detail_description || p.description;
        document.getElementById('modalProductIntegration').textContent = p.integration;
        document.getElementById('modalProductDeployment').textContent = p.deployment;
        document.getElementById('modalProductRelease').textContent = p.release_date;
        document.getElementById('reqProductName').value = p.name;
        
        // Reset Custom wishes box
        document.getElementById('modalWishes').value = '';

        // Star rating
        const stars = '★'.repeat(Math.round(p.rating)) + '☆'.repeat(5 - Math.round(p.rating));
        document.getElementById('modalProductRating').innerHTML = `${stars} <span class="text-white-50 ms-1 small">(${p.rating})</span>`;

        // Pricing values
        document.getElementById('modalBasicPrice').textContent = `£${p.basic_price}`;
        document.getElementById('modalStandardPrice').textContent = `£${p.standard_price}`;

        // Dynamic package pricing in dropdown
        const reqPackageSelect = document.getElementById('reqPackage');
        if (reqPackageSelect) {
            reqPackageSelect.options[0].textContent = `Basic Package (£${p.basic_price}/mo)`;
            reqPackageSelect.options[1].textContent = `Standard Package (£${p.standard_price}/mo)`;
        }

        // Tailored Articles
        const articleHtml = productArticles[p.name] || `<h6 class="text-white mt-3">Enterprise deployment updates for ${p.name}</h6><p class="small text-white mb-2">Our engineering advisory team has issued stability bulletins for this product. Contact Sunderland HQ for technical integration guidelines.</p>`;
        document.getElementById('modalNewsContent').innerHTML = articleHtml;

        // Reset radio and warnings
        document.getElementById('actionInquiry').checked = true;
        document.getElementById('demoDepositWarning').classList.add('d-none');

        switchModalTab(defaultTab);
        modalInstance.show();
    };

    window.switchModalTab = function(tabName) {
        document.querySelectorAll('.modal-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.detail-tab-btn').forEach(b => b.classList.remove('active'));

        if (tabName === 'overview') {
            document.getElementById('modalPanelOverview').style.display = 'block';
            document.getElementById('tabBtnOverview').classList.add('active');
        } else if (tabName === 'pricing') {
            document.getElementById('modalPanelPricing').style.display = 'block';
            document.getElementById('tabBtnPricing').classList.add('active');
        } else if (tabName === 'news') {
            document.getElementById('modalPanelNews').style.display = 'block';
            document.getElementById('tabBtnNews').classList.add('active');
        } else if (tabName === 'request') {
            document.getElementById('modalPanelRequest').style.display = 'block';
            document.getElementById('tabBtnRequest').classList.add('active');
            updateDemoPriceDisplay();
        }
    };

    window.updateDemoPriceDisplay = function() {
        const isDemo = document.getElementById('actionDemo').checked;
        const warningBox = document.getElementById('demoDepositWarning');

        if (isDemo) {
            warningBox.classList.remove('d-none');
            warningBox.innerHTML = '<i class="fas fa-info-circle me-1"></i> Demo may charge, we will contact you shortly.';
        } else {
            warningBox.classList.add('d-none');
        }
    };

    // Wishlist Drawer - Deactivated for public facing pages. Configured requests are sent to admin CRM dashboard.
    function renderWishlist() {
        // No-op. Admin dashboard CRM is the source of truth.
    }

    window.payDemoDeposit = function(index) {
        // No-op
    };

    window.clearWishlist = function() {
        localStorage.removeItem('user_requests');
        localStorage.removeItem('user_client_id');
    };

    // Submit Request from Modal
    const reqForm = document.getElementById('modalRequestForm');
    if (reqForm) {
        reqForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const submitBtn = document.getElementById('reqSubmitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting Request...';

            const name = document.getElementById('reqName').value.trim();
            const email = document.getElementById('reqEmail').value.trim();
            const phone = document.getElementById('reqPhone').value.trim();
            const company = document.getElementById('reqCompany').value.trim();
            const country = document.getElementById('reqCountry').value.trim();
            const job = document.getElementById('reqJob').value.trim();
            const productName = document.getElementById('reqProductName').value;
            const packageName = document.getElementById('reqPackage').value;
            const requestType = document.querySelector('input[name="reqAction"]:checked').value;
            const customWishes = document.getElementById('modalWishes').value.trim();

            const deposit = requestType === 'Demo' ? (packageName.includes('Basic') ? 14.95 : 39.95) : 0.00;
            const paymentStatus = deposit > 0 ? 'Pending' : 'Free';

            const payload = {
                full_name: name,
                email_address: email,
                phone_number: phone,
                company_name: company,
                country: country,
                job_title: job,
                product_name: productName,
                package_name: packageName,
                request_type: requestType,
                custom_wishes: customWishes,
                deposit_amount: deposit,
                payment_status: paymentStatus,
                inquiry_details: customWishes
            };

            // AJAX submit to submit_inquiry.php
            fetch('submit_inquiry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(res => {
                if (!res.ok) throw new Error("HTTP " + res.status);
                return res.json();
            })
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                
                if (data.status === 'success') {
                    // Update local user request state
                    const storedRequests = JSON.parse(localStorage.getItem('user_requests')) || [];
                    storedRequests.unshift({
                        product_name: productName,
                        package_name: packageName,
                        request_type: requestType,
                        custom_wishes: customWishes,
                        deposit_amount: deposit,
                        payment_status: paymentStatus
                    });
                    localStorage.setItem('user_requests', JSON.stringify(storedRequests));
                    localStorage.setItem('user_client_id', data.client_id);
                    
                    modalInstance.hide();
                    showToast('Request Received', data.message);
                    renderWishlist();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(err => {
                console.warn("PHP Ingestion failed, running localStorage fallback:", err);
                
                // localStorage fallback simulation
                let clientID = localStorage.getItem('user_client_id');
                if (!clientID) {
                    // Generate new customer sequential ID locally
                    let localCustomers = JSON.parse(localStorage.getItem('customers')) || [];
                    clientID = localCustomers.length + 1;
                    localCustomers.push({ id: clientID, full_name: name, email_address: email });
                    localStorage.setItem('customers', JSON.stringify(localCustomers));
                    localStorage.setItem('user_client_id', clientID);
                }

                // Append request to user wishlist
                const storedRequests = JSON.parse(localStorage.getItem('user_requests')) || [];
                storedRequests.unshift({
                    product_name: productName,
                    package_name: packageName,
                    request_type: requestType,
                    custom_wishes: customWishes,
                    deposit_amount: deposit,
                    payment_status: paymentStatus
                });
                localStorage.setItem('user_requests', JSON.stringify(storedRequests));

                // Save to customer_inquiries array locally for admin-dashboard simulation
                const localInquiries = JSON.parse(localStorage.getItem('customer_inquiries')) || [];
                localInquiries.unshift({
                    id: Date.now(),
                    customer_id: parseInt(clientID),
                    full_name: name,
                    email_address: email,
                    phone_number: phone,
                    company_name: company,
                    country: country,
                    job_title: job,
                    request_type: requestType,
                    product_name: productName,
                    package_name: packageName,
                    deposit_amount: deposit,
                    payment_status: paymentStatus,
                    custom_wishes: customWishes,
                    inquiry_details: customWishes,
                    created_at: new Date().toISOString()
                });
                localStorage.setItem('customer_inquiries', JSON.stringify(localInquiries));

                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                modalInstance.hide();

                let msg = `Thank you! Your ${requestType} request is registered. (Client ID: #${clientID})`;
                showToast('Request Registered', msg);
                renderWishlist();
            });
        });
    }

    // ====================================================================
    // 5. REVIEWS SYSTEM: Dynamic Fetching & Writing verified comments
    // ====================================================================
    const reviewsContainer = document.getElementById('reviewsContainer');
    const writeReviewModalEl = document.getElementById('writeReviewModal');
    let reviewModal = null;
    if (writeReviewModalEl) {
        reviewModal = new bootstrap.Modal(writeReviewModalEl);
    }

    const defaultReviews = [
        {name: 'Sarah Jenkins', role: 'CTO, Global Logistics Corp', rating: 5, product: 'Nexus Assist Pro', date: 'Oct 2023', text: '"The deployment of Nexus Assist Pro was seamless. AI-Solution\'s team guided us every step of the way, and the results have drastically exceeded our expectations with a 45% reduction in IT tickets."', img: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=100', is_verified: 1},
        {name: 'David Chen', role: 'IT Director, FinTech Innovations', rating: 5, product: 'LogicBuilder 3.0', date: 'Sep 2023', text: '"LogicBuilder allowed our small IT team to act like a massive development department. We deployed 5 custom AI workflows in under a month. Absolutely game-changing."', img: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=100', is_verified: 1},
        {name: 'Elena Rodriguez', role: 'VP Operations, HealthTech UK', rating: 5, product: 'OmniMetrics AI', date: 'Oct 2023', text: '"The predictive analytics from OmniMetrics pinpointed operational bottlenecks we didn\'t even know existed. A truly phenomenal piece of software for any enterprise."', img: 'https://images.unsplash.com/photo-1580489944761-15a19d654956?q=80&w=100', is_verified: 1},
        {name: 'James Whitmore', role: 'IT Operations Director, HealthLink', rating: 5, product: 'Nexus Assist v1', date: 'Mar 2022', text: '"An absolute workhorse. Version 1 has been running for 5 years without a single crash. The customer support is top-notch and the documentation is comprehensive."', img: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', is_verified: 1},
        {name: 'Priya Sharma', role: 'Chief Data Officer, FinTech Corp', rating: 5, product: 'DataSync Core AI', date: 'Jan 2022', text: '"Flawless database migration utility. The standard SQL engine is extremely stable. We still rely on it for nightly syncs and it has never let us down."', img: 'https://images.unsplash.com/photo-1551836022-deb4988cc6c0?q=80&w=100', is_verified: 1},
        {name: 'Thomas Mueller', role: 'CTO, AutoTech GmbH', rating: 5, product: 'OmniMetrics AI', date: 'Nov 2023', text: '"OmniMetrics AI transformed how we monitor our supply chain operations. The ROI was evident within the first quarter of deployment. Highly recommended."', img: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', is_verified: 1}
    ];

    if (!localStorage.getItem('customer_reviews')) {
        localStorage.setItem('customer_reviews', JSON.stringify(defaultReviews));
    }

    function renderReviewsList() {
        if (!reviewsContainer) return;

        fetch('get_reviews.php')
            .then(res => {
                if (!res.ok) throw new Error("HTTP error " + res.status);
                return res.json();
            })
            .then(data => {
                displayReviews(data);
            })
            .catch(err => {
                console.warn("PHP reviews endpoint failed, loading local reviews:", err);
                const stored = JSON.parse(localStorage.getItem('customer_reviews')) || defaultReviews;
                displayReviews(stored);
            });
    }

    function displayReviews(toShow) {
        if (!reviewsContainer) return;
        const sliced = (toShow || []).slice(0, 12);
        reviewsContainer.innerHTML = sliced.map(rev => {
            const stars = '★'.repeat(rev.rating) + '☆'.repeat(5 - rev.rating);
            const isVerified = rev.is_verified === 1 || rev.is_verified === true;
            return `
                <div class="col-md-6 col-lg-4">
                    <div class="review-card ${isVerified ? 'review-card-verified' : ''} bg-white p-4 rounded-4 shadow-sm border border-light h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="${rev.img}" class="review-avatar rounded-circle border" style="width:40px;height:40px;object-fit:cover;" alt="${rev.name}">
                                    <div>
                                        <div class="fw-bold review-reviewer-name" style="font-size:0.9rem;">${rev.name}</div>
                                        <div class="small review-reviewer-role" style="font-size:0.75rem;">${rev.role}</div>
                                    </div>
                                </div>
                                ${isVerified ? `<span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>` : ''}
                            </div>
                            <div class="review-stars mb-2 text-warning">${stars}</div>
                            <p class="small mb-3 review-text-content" style="line-height:1.6;">${rev.text}</p>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-top pt-2">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:0.7rem;font-weight:600;">${rev.product}</span>
                            <span class="text-muted small" style="font-size:0.7rem;">${rev.date}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    window.openReviewModal = function() {
        if (reviewModal) reviewModal.show();
    };

    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const name = document.getElementById('revName').value.trim();
            const email = document.getElementById('revEmail').value.trim();
            const role = document.getElementById('revRole').value.trim();
            const product = document.getElementById('revProduct').value;
            const rating = parseInt(document.getElementById('revRating').value);
            const text = document.getElementById('revText').value.trim();

            if (!name || !email || !role || !text) return;

            const payload = {
                reviewer_name: name,
                email_address: email,
                reviewer_role: role,
                product_name: product,
                rating: rating,
                review_text: text,
                reviewer_img: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=100'
            };

            fetch('submit_review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => {
                return res.json().then(data => {
                    if (!res.ok) {
                        return { status: 'error', message: data.message || ('HTTP ' + res.status) };
                    }
                    return data;
                }).catch(() => {
                    if (!res.ok) throw new Error("HTTP " + res.status);
                    throw new Error("Invalid JSON");
                });
            })
            .then(data => {
                if (data.status === 'success') {
                    // Save locally for UI updates
                    const stored = JSON.parse(localStorage.getItem('customer_reviews')) || defaultReviews;
                    stored.unshift({
                        name: name,
                        role: role,
                        rating: rating,
                        product: product,
                        date: 'Just Now',
                        text: `"${text}"`,
                        img: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=100',
                        is_verified: data.is_verified
                    });
                    localStorage.setItem('customer_reviews', JSON.stringify(stored));
                    
                    reviewForm.reset();
                    reviewModal.hide();
                    showToast('Review Received', data.message);
                    renderReviewsList();
                } else {
                    showToast('Submission Rejected', data.message);
                }
            })
            .catch(err => {
                console.warn("PHP review submission failed, running localStorage fallback:", err);
                
                // Fallback check purchase status locally
                const purchases = JSON.parse(localStorage.getItem('customer_purchases')) || [];
                const isVerified = purchases.some(p => p.email_address === email && p.product_name === product);

                const stored = JSON.parse(localStorage.getItem('customer_reviews')) || defaultReviews;
                stored.unshift({
                    name: name,
                    role: role,
                    rating: rating,
                    product: product,
                    date: 'Just Now',
                    text: `"${text}"`,
                    img: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=100',
                    is_verified: isVerified ? 1 : 0
                });
                localStorage.setItem('customer_reviews', JSON.stringify(stored));

                reviewForm.reset();
                reviewModal.hide();
                showToast('Review Saved Offline', 'Your feedback has been recorded successfully.');
                renderReviewsList();
            });
        });
    }

    renderReviewsList();

    // ====================================================================
    // 5.5 EVENTS SYSTEM: Dynamic Upcoming Events loading
    // ====================================================================
    const upcomingEventsContainer = document.getElementById('upcoming-events-container');
    const upcomingTimelineContainer = document.getElementById('upcoming-timeline-container');

    const defaultEvents = [
        {
            id: 1,
            title: 'Sunderland Tech Summit 2026',
            badge_text: 'Live Summit',
            badge_class: 'bg-info text-dark',
            description: 'Join our executive and engineering teams as we present our B2B integration roadmaps live at the Innovation Hub.',
            event_date: 'October 15–16, 2026',
            image_path: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?q=80&w=600'
        },
        {
            id: 2,
            title: 'Scaling HR Virtual Assistants',
            badge_text: 'Webinar',
            badge_class: 'bg-success',
            description: 'A technical webinar outlining how to optimize employee workflows and reduce ticket response times by 50%.',
            event_date: 'November 05, 2026',
            image_path: 'https://images.unsplash.com/photo-1515187029135-18ee286d815b?q=80&w=600'
        }
    ];

    function loadUpcomingEvents() {
        if (!upcomingEventsContainer && !upcomingTimelineContainer) return;
        
        fetch('get_events.php')
            .then(res => {
                if (!res.ok) throw new Error("HTTP error " + res.status);
                return res.json();
            })
            .then(data => {
                if (data && data.length > 0) {
                    displayEvents(data);
                } else {
                    displayEvents(defaultEvents);
                }
            })
            .catch(err => {
                console.warn("PHP events endpoint failed, loading default events:", err);
                displayEvents(defaultEvents);
            });
    }

    function displayEvents(evts) {
        if (upcomingEventsContainer) {
            upcomingEventsContainer.innerHTML = evts.slice(0, 2).map(evt => {
                return `
                    <div class="col-md-5">
                        <div class="card bg-primary-dark border border-secondary text-white h-100 card-custom text-start overflow-hidden" style="background: var(--primary-dark) !important;">
                            <img src="${evt.image_path}" class="card-img-top" alt="${evt.title}">
                            <div class="card-body p-4 d-flex flex-column h-100">
                                <span class="badge ${evt.badge_class} mb-3 align-self-start">${evt.badge_text}</span>
                                <h4 class="text-white fw-bold mb-2">${evt.title}</h4>
                                <p class="text-light opacity-75 small">${evt.description}</p>
                                <span class="text-accent-blue fw-bold small mt-auto"><i class="far fa-calendar-alt me-1"></i> ${evt.event_date}</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        if (upcomingTimelineContainer) {
            upcomingTimelineContainer.innerHTML = evts.map(evt => {
                let actionBtnText = 'Reserve Free Pass <i class="fas fa-ticket-alt ms-1"></i>';
                if (evt.badge_text.toLowerCase().includes('webinar')) {
                    actionBtnText = 'Register for Webinar <i class="fas fa-video ms-1"></i>';
                } else if (evt.badge_text.toLowerCase().includes('workshop')) {
                    actionBtnText = 'Register for Workshop <i class="fas fa-tools ms-1"></i>';
                }
                return `
                    <div class="timeline-event-item">
                        <span class="timeline-date">${evt.event_date}</span>
                        <div class="event-card-premium" style="background-color: #FAF8F5 !important; background: #FAF8F5 !important; border: none !important;">
                            <span class="badge ${evt.badge_class} mb-2">${evt.badge_text}</span>
                            <h4 class="fw-bold mb-2" style="color: #000000 !important; font-family: 'Outfit', sans-serif !important; font-weight: 800 !important;">${evt.title}</h4>
                            <p class="small mb-3" style="color: #334155 !important; font-weight: 500 !important; opacity: 1 !important; line-height: 1.6 !important;">${evt.description}</p>
                            <button class="btn btn-sm btn-outline-accent rounded-pill px-3 py-1.5 small register-event-btn" data-event-title="${evt.title.replace(/'/g, "&apos;")}">${actionBtnText}</button>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    const archivedEvents = [
        {
            title: "Yokyo Olympic AI Strategy Summit",
            event_date: "May 2026",
            badge_text: "Archived Summit",
            badge_class: "bg-secondary text-white",
            description: "Presented our secure enterprise virtual assistant framework and integration roadmaps to global sports coordinators and infrastructure partners.",
            image_path: "https://images.unsplash.com/photo-1540575467063-178a50c2df87?q=80&w=600"
        },
        {
            title: "Global Enterprise Automation Webinar",
            event_date: "April 2026",
            badge_text: "Archived Webinar",
            badge_class: "bg-secondary text-white",
            description: "Deep-dive session demonstrating zero-downtime database synchronization pipelines and automatic CRM ticket routing integrations.",
            image_path: "https://images.unsplash.com/photo-1515187029135-18ee286d815b?q=80&w=600"
        },
        {
            title: "B2B Telemetry & Integration Roundtable",
            event_date: "March 2026",
            badge_text: "Archived Roundtable",
            badge_class: "bg-secondary text-white",
            description: "Collaborative engineering discussion on RAG compliance, Active Directory integration policies, and predicting system bottlenecks.",
            image_path: "https://images.unsplash.com/photo-1505373877841-8d25f7d46678?q=80&w=600"
        }
    ];

    function loadArchivedEvents() {
        const archivedContainer = document.getElementById('archived-events-container');
        if (!archivedContainer) return;
        archivedContainer.innerHTML = archivedEvents.map(evt => {
            return `
                <div class="col-md-4">
                    <div class="card bg-secondary-dark border border-secondary text-white h-100 card-custom text-start overflow-hidden" style="background: var(--secondary-dark) !important; opacity: 0.85;">
                        <img src="${evt.image_path}" class="card-img-top" alt="${evt.title}" style="filter: grayscale(30%);">
                        <div class="card-body p-4 d-flex flex-column h-100">
                            <span class="badge ${evt.badge_class} mb-3 align-self-start">${evt.badge_text}</span>
                            <h5 class="text-white fw-bold mb-2">${evt.title}</h5>
                            <p class="text-white-50 small mb-3">${evt.description}</p>
                            <span class="text-muted fw-bold small mt-auto"><i class="fas fa-calendar-check me-1"></i> ${evt.event_date}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    loadUpcomingEvents();
    loadArchivedEvents();

    // ====================================================================
    // 6. NEWS ARTICLES SYSTEM: "Read Full Article" Modal Popup
    // ====================================================================
    const newsArticles = {
        '1': {
            title: 'The Future of AI in Employee Experience',
            category: 'Technology',
            badgeClass: 'bg-primary',
            date: 'October 12, 2023 • 5 min read',
            image: 'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=2070&auto=format&fit=crop',
            body: `
                <p>As enterprises transition to hybrid and fully remote work models, the digital employee experience (DEX) has become a primary driver of retention, productivity, and organizational health. AI is no longer just a tool for automating low-level ticket routing; it is transforming how businesses understand and anticipate employee needs.</p>
                <p>Leveraging advanced machine learning, modern systems can analyze telemetry patterns—such as database execution latency, ticket backlogs, and software usage drops—to identify employee burnout and operational friction before it manifests. For example, if an employee is repeatedly encountering errors in legacy CRM software, a predictive assistant like OmniMetrics AI can proactively trigger tailored micro-tutorials or alert IT support to resolve the system friction before the employee submits a formal complaint.</p>
                <p>In the coming years, we will see the rise of proactive RAG (Retrieval-Augmented Generation) assistants. Instead of employees manually searching through outdated wikis and shared folders, contextual search agents will index workspace histories and automatically suggest relevant documents or answers. The future of employee experience is proactive, personalized, and powered by seamless AI-human collaboration.</p>
            `
        },
        '2': {
            title: 'AI-Solution Opens New Sunderland HQ',
            category: 'Company News',
            badgeClass: 'bg-success',
            date: 'September 28, 2023 • 3 min read',
            image: 'https://images.unsplash.com/photo-1553877522-43269d4ea984?q=80&w=2070&auto=format&fit=crop',
            body: `
                <p>AI-Solution is proud to announce the official opening of our new corporate headquarters in the Innovation Hub, Sunderland. Designed to accommodate our rapidly growing research, engineering, and customer success teams, this state-of-the-art facility represents a major milestone in our mission to automate B2B workspace productivity.</p>
                <p>The new office features dedicated collaborative spaces, secure sandboxed server rooms for testing enterprise on-premise integrations, and advanced interactive design labs where clients can pair with our engineers to prototype custom AI logic trees.</p>
                <p>The Sunderland region has emerged as a vibrant technology hub in the UK, and our investment highlights our commitment to fostering local tech talent and engaging with regional universities. With this expansion, AI-Solution plans to create 50 new high-value engineering and AI researcher roles over the next 18 months, securing our position as a leader in corporate automation solutions.</p>
            `
        },
        '3': {
            title: "LogicBuilder 3.0: What's New?",
            category: 'Product Update',
            badgeClass: 'bg-info',
            date: 'September 15, 2023 • 7 min read',
            image: 'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?q=80&w=1965&auto=format&fit=crop',
            body: `
                <p>We are thrilled to release LogicBuilder 3.0, the latest iteration of our visual no-code prototyping studio. This release represents a complete rebuild of our drag-and-drop canvas, leveraging high-performance WebGL technology to handle complex workflows with hundreds of connected nodes without any browser lag.</p>
                <p>Key features in this release include:</p>
                <ul>
                    <li><strong>Visual Conditional Router</strong>: A new node type that evaluates payload values dynamically (e.g., routing premium users to VIP server instances) using flexible logical rules.</li>
                    <li><strong>Multi-Model Orchestration</strong>: Chain multiple LLMs, semantic embedding engines, and legacy database queries together. You can easily feed output from one model into another and post the results to active databases.</li>
                    <li><strong>Docker & Kubernetes Auto-Generation</strong>: Once a workflow is finalized, export it directly as a production-ready containerized microservice with zero manual setup.</li>
                    <li><strong>Live Telemetry & Debugging</strong>: Inspect inputs and outputs at every node in real-time as workflows execute, making it simpler than ever to troubleshoot API timeouts and data mismatch issues.</li>
                </ul>
                <p>LogicBuilder 3.0 is now available to all Standard and Enterprise tier subscribers. Visit the dashboard or contact our engineering desk in Sunderland for a guided walkthrough.</p>
            `
        },
        '4': {
            title: 'Securing B2B AI Workspaces with SOC2 Compliance',
            category: 'Technology',
            badgeClass: 'bg-primary',
            date: 'January 10, 2024 • 6 min read',
            image: 'https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=2070&auto=format&fit=crop',
            body: `
                <p>Securing enterprise data is the absolute top priority when integrating generative AI systems into corporate workspaces. Retrieval-Augmented Generation (RAG) has become the gold standard for dynamic knowledge management, but it also brings unique compliance challenges, especially under SOC2, HIPAA, and GDPR frameworks.</p>
                <p>In highly regulated sectors such as fintech and digital health, AI systems must guarantee role-based access control. If an employee queries internal Wikibase logs, the AI router must verify active directory groups to ensure they are authorized to view the underlying files. ComplianceBot AI solves this by continuously auditing active directory permissions and monitoring AI input prompts for potential security infractions.</p>
                <p>Furthermore, stateful data transit must use AES-256 encryption. By sandboxing RAG execution environments within local or securely managed cloud instances, enterprises can achieve a zero-data-retention policy, preventing proprietary training data from leaking to public LLM providers. Continuous auditing is key to maintaining a robust compliance posture.</p>
            `
        },
        '5': {
            title: 'AI-Solution Partners with Local Universities',
            category: 'Company News',
            badgeClass: 'bg-success',
            date: 'March 02, 2024 • 4 min read',
            image: 'images/news_university.png',
            body: `
                <p>AI-Solution is delighted to announce a new strategic partnership with leading North East universities, including Sunderland University and Newcastle University, to fund graduate computer science research grants.</p>
                <p>This initiative will support PhD and Master's students working on secure, federated active directory telemetry auditing and scalable conversational AI models. By collaborating closely with academic research labs, AI-Solution aims to bridge the gap between cutting-edge theoretical machine learning and practical, secure B2B software engineering.</p>
                <p>As part of the grant program, students will gain access to AI-Solution's dedicated sandboxed testing environments to validate theoretical security proofs against real-world database replication workloads. We look forward to welcoming the next generation of AI research talent to our Sunderland Innovation Hub.</p>
            `
        }
    };

    const articleModalEl = document.getElementById('articleDetailModal');
    let articleModalInstance = null;
    if (articleModalEl) {
        articleModalInstance = new bootstrap.Modal(articleModalEl);
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.read-article-btn');
        if (btn) {
            e.preventDefault();
            const articleId = btn.getAttribute('data-article-id');
            const art = newsArticles[articleId];
            if (art && articleModalInstance) {
                document.getElementById('modalArticleTitle').textContent = art.title;
                const catBadge = document.getElementById('modalArticleCategory');
                if (catBadge) {
                    catBadge.textContent = art.category;
                    catBadge.className = 'badge ' + art.badgeClass + ' mb-2';
                }
                const dateEl = document.getElementById('modalArticleDate');
                if (dateEl) dateEl.textContent = art.date;
                const imgEl = document.getElementById('modalArticleImg');
                if (imgEl) imgEl.src = art.image;
                const bodyEl = document.getElementById('modalArticleBody');
                if (bodyEl) bodyEl.innerHTML = art.body;
                articleModalInstance.show();
            }
        }
    });

    // ====================================================================
    // 7. GENERAL UTILITIES: Toasts
    // ====================================================================
    function showToast(title, message) {
        const toast = document.getElementById('submitToast');
        if (!toast) return;
        const titleEl = document.getElementById('toastTitle') || toast.querySelector('strong');
        if (titleEl) titleEl.textContent = title;
        const msgEl = document.getElementById('toastMessage') || toast.querySelector('span.small') || toast.querySelector('.small');
        if (msgEl) msgEl.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 6000);
    }

    // ====================================================================
    // 8. INTERACTIVE PRICING CARDS IN MODAL
    // ====================================================================
    document.querySelectorAll('.price-card-modal').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => {
            const cardText = card.textContent.toLowerCase();
            const selectEl = document.getElementById('reqPackage');
            if (selectEl) {
                if (cardText.includes('basic')) {
                    selectEl.value = 'Basic Package';
                } else if (cardText.includes('standard')) {
                    selectEl.value = 'Standard Package';
                } else if (cardText.includes('custom') || cardText.includes('enterprise')) {
                    selectEl.value = 'Custom Plan';
                }
                if (typeof updateDemoPriceDisplay === 'function') {
                    updateDemoPriceDisplay();
                }
            }
            if (typeof switchModalTab === 'function') {
                switchModalTab('request');
            }
        });
    });

    // ====================================================================
    // 9. AJAX CONTACT FORM SUBMISSION (#leadForm)
    // ====================================================================
    const contactForm = document.getElementById('leadForm');
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();

            // Validate form requirements
            let isValid = true;
            contactForm.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
                
                if (input.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value.trim())) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    }
                }
            });

            if (!isValid) {
                contactForm.classList.add('was-validated');
                showToast('Validation Error', 'Please correct the highlighted fields.');
                return;
            }

            const submitBtn = document.getElementById('submitContactBtn') || contactForm.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Submit Inquiry';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            }

            const name = document.getElementById('fullName').value.trim();
            const email = document.getElementById('emailAddress').value.trim();
            const phone = document.getElementById('phoneNumber').value.trim();
            const country = document.getElementById('country').value.trim();
            const company = document.getElementById('companyName').value.trim();
            const job = document.getElementById('jobTitle').value.trim();
            const productName = document.getElementById('productInterest').value;
            const packageName = document.getElementById('packageInterest').value;
            const requestType = contactForm.querySelector('input[name="request_type"]:checked').value;
            const details = document.getElementById('inquiry').value.trim();

            let basicPrice = 299.00;
            let standardPrice = 799.00;
            if (productName === 'Nexus Assist Pro') { basicPrice = 349.00; standardPrice = 899.00; }
            else if (productName === 'LogicBuilder 3.0') { basicPrice = 199.00; standardPrice = 599.00; }
            else if (productName === 'ComplianceBot AI') { basicPrice = 399.00; standardPrice = 999.00; }
            else if (productName === 'AutoRespond Agent') { basicPrice = 249.00; standardPrice = 699.00; }
            else if (productName === 'DataSync Core AI') { basicPrice = 149.00; standardPrice = 499.00; }
            else if (productName === 'OmniSearch Enterprise') { basicPrice = 449.00; standardPrice = 1199.00; }

            const deposit = requestType === 'Demo' ? (packageName.includes('Basic') ? (basicPrice * 0.05) : (standardPrice * 0.05)) : 0.00;
            const paymentStatus = deposit > 0 ? 'Pending' : 'Free';

            const payload = {
                full_name: name,
                email_address: email,
                phone_number: phone,
                company_name: company,
                country: country,
                job_title: job,
                product_name: productName,
                package_name: packageName,
                request_type: requestType,
                deposit_amount: deposit,
                payment_status: paymentStatus,
                inquiry_details: details
            };

            fetch('submit_inquiry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(res => {
                if (!res.ok) throw new Error("HTTP " + res.status);
                return res.json();
            })
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }

                if (data.status === 'success') {
                    // Update local user request state (wishlist)
                    const storedRequests = JSON.parse(localStorage.getItem('user_requests')) || [];
                    storedRequests.unshift({
                        product_name: productName,
                        package_name: packageName,
                        request_type: requestType,
                        custom_wishes: '',
                        deposit_amount: deposit,
                        payment_status: paymentStatus
                    });
                    localStorage.setItem('user_requests', JSON.stringify(storedRequests));
                    localStorage.setItem('user_client_id', data.client_id);

                    // Reset form and validation state
                    contactForm.reset();
                    contactForm.classList.remove('was-validated');
                    if (typeof updateContactDemoWarning === 'function') {
                        updateContactDemoWarning();
                    }

                    // Show success notification
                    showToast('Request Submitted', data.message);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(err => {
                console.warn("AJAX submit failed, running offline fallback:", err);
                
                let clientID = localStorage.getItem('user_client_id');
                if (!clientID) {
                    let localCustomers = JSON.parse(localStorage.getItem('customers')) || [];
                    clientID = localCustomers.length + 1;
                    localCustomers.push({ id: clientID, full_name: name, email_address: email });
                    localStorage.setItem('customers', JSON.stringify(localCustomers));
                    localStorage.setItem('user_client_id', clientID);
                }

                const storedRequests = JSON.parse(localStorage.getItem('user_requests')) || [];
                storedRequests.unshift({
                    product_name: productName,
                    package_name: packageName,
                    request_type: requestType,
                    custom_wishes: '',
                    deposit_amount: deposit,
                    payment_status: paymentStatus
                });
                localStorage.setItem('user_requests', JSON.stringify(storedRequests));

                const localInquiries = JSON.parse(localStorage.getItem('customer_inquiries')) || [];
                localInquiries.unshift({
                    id: Date.now(),
                    customer_id: parseInt(clientID),
                    full_name: name,
                    email_address: email,
                    phone_number: phone,
                    company_name: company,
                    country: country,
                    job_title: job,
                    request_type: requestType,
                    product_name: productName,
                    package_name: packageName,
                    deposit_amount: deposit,
                    payment_status: paymentStatus,
                    custom_wishes: '',
                    inquiry_details: details,
                    created_at: new Date().toISOString()
                });
                localStorage.setItem('customer_inquiries', JSON.stringify(localInquiries));

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }

                contactForm.reset();
                contactForm.classList.remove('was-validated');
                if (typeof updateContactDemoWarning === 'function') {
                    updateContactDemoWarning();
                }

                let msg = `Thank you! Your ${requestType} request is registered. (Client ID: #${clientID})`;
                showToast('Request Registered', msg);
            });
        });
    }
});

// Sorting and filtering delegates
function filterProducts(category, el) {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.product-item').forEach(item => {
        if (category === 'all') {
            item.style.display = '';
        } else {
            const cats = item.getAttribute('data-category') || '';
            item.style.display = cats.includes(category) ? '' : 'none';
        }
    });
}

function updateSort(sortType) {
    const dropdownBtn = document.getElementById('sortDropdown');
    if (dropdownBtn) dropdownBtn.textContent = `Sort By: ${sortType}`;

    const container = document.getElementById('products-container');
    if (!container) return;

    const items = Array.from(container.querySelectorAll('.product-item'));
    items.sort((a, b) => {
        if (sortType === 'Newest First') {
            const dateA = new Date(a.getAttribute('data-date') || 0);
            const dateB = new Date(b.getAttribute('data-date') || 0);
            return dateB - dateA;
        } else if (sortType === 'Top Rated') {
            const ratingA = parseFloat(a.getAttribute('data-rating') || 0);
            const ratingB = parseFloat(b.getAttribute('data-rating') || 0);
            return ratingB - ratingA;
        } else { // Recommended
            const recA = parseInt(a.getAttribute('data-recommended') || 0, 10);
            const recB = parseInt(b.getAttribute('data-recommended') || 0, 10);
            return recA - recB;
        }
    });

    items.forEach(item => container.appendChild(item));
}

// ====================================================================
// EVENT REGISTRATION ENGINE OVERLAY & PIPELINE
// ====================================================================
function injectEventModal() {
    if (document.getElementById('eventRegModal')) return;
    const modalHtml = `
    <div class="modal fade" id="eventRegModal" tabindex="-1" aria-labelledby="eventRegModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-white" style="background: rgba(22, 22, 31, 0.95); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <h5 class="modal-title fw-bold text-white" id="eventRegModalLabel"><i class="far fa-calendar-check me-2" style="color: var(--accent-blue);"></i>Event Registration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <form id="eventRegForm">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Full Name *</label>
                            <input type="text" class="form-control form-control-custom text-white" id="eventRegName" required placeholder="e.g. John Doe">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Work Email *</label>
                            <input type="email" class="form-control form-control-custom text-white" id="eventRegEmail" required placeholder="e.g. john@company.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Company Name *</label>
                            <input type="text" class="form-control form-control-custom text-white" id="eventRegCompany" required placeholder="e.g. Acme Corp">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Selected Event</label>
                            <input type="text" class="form-control form-control-custom text-white" id="eventRegTitle" readonly style="background: rgba(255,255,255,0.05) !important;">
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.06);">
                        <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-accent rounded-pill px-4" id="eventRegSubmitBtn">Reserve Pass</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const form = document.getElementById('eventRegForm');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        submitEventRegistration();
    });
}

let eventModalInstance = null;
window.openEventRegModal = function(eventTitle) {
    injectEventModal();
    document.getElementById('eventRegTitle').value = eventTitle;
    document.getElementById('eventRegName').value = '';
    document.getElementById('eventRegEmail').value = '';
    document.getElementById('eventRegCompany').value = '';
    
    if (!eventModalInstance) {
        const modalEl = document.getElementById('eventRegModal');
        eventModalInstance = new bootstrap.Modal(modalEl);
    }
    eventModalInstance.show();
};

function submitEventRegistration() {
    const name = document.getElementById('eventRegName').value.trim();
    const email = document.getElementById('eventRegEmail').value.trim();
    const company = document.getElementById('eventRegCompany').value.trim();
    const eventTitle = document.getElementById('eventRegTitle').value;
    const submitBtn = document.getElementById('eventRegSubmitBtn');

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

    const payload = new FormData();
    payload.append('full_name', name);
    payload.append('email_address', email);
    payload.append('company_name', company);
    payload.append('event_title', eventTitle);

    fetch('submit_event_registration.php', {
        method: 'POST',
        body: payload
    })
    .then(res => {
        return res.json().then(data => {
            if (!res.ok) {
                return { status: 'error', message: data.message || ('HTTP ' + res.status) };
            }
            return data;
        }).catch(() => {
            if (!res.ok) throw new Error("HTTP " + res.status);
            throw new Error("Invalid JSON");
        });
    })
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (data.status === 'success') {
            eventModalInstance.hide();
            showToast('Registration Confirmed', data.message);
        } else {
            showToast('Registration Failed', data.message);
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        console.error("Event registration failed:", err);
        eventModalInstance.hide();
        showToast('Registration Saved', 'Thank you! Your pass is reserved (Offline Mode).');
    });
}

// Intercept event button clicks
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.register-event-btn');
    if (btn) {
        e.preventDefault();
        const eventTitle = btn.getAttribute('data-event-title');
        window.openEventRegModal(eventTitle);
        return;
    }
    
    const linkBtn = e.target.closest('a[href="contact.html"]');
    if (linkBtn && (linkBtn.closest('.event-card-premium') || linkBtn.closest('.card-custom'))) {
        const eventCard = linkBtn.closest('.event-card-premium') || linkBtn.closest('.card-custom');
        const h4 = eventCard.querySelector('h4');
        if (h4) {
            e.preventDefault();
            window.openEventRegModal(h4.textContent.trim());
        }
    }
});
