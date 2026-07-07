<?php
/**
 * db_connect.php
 * 
 * Centralized SQLite connection and auto-initialization utility.
 * Connects via PDO and sets up tables & seeds initial data if database is fresh.
 */

$db_file = __DIR__ . '/ai_solution_db.sqlite';

try {
    // Establish PDO SQLite Connection
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable Foreign Keys
    $pdo->exec('PRAGMA foreign_keys = ON;');
    
    // 1. Create admin_users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `username` TEXT NOT NULL UNIQUE,
        `password` TEXT NOT NULL
    )");
    
    // Seed default admin if missing or hash plain-text password if it exists
    $stmt = $pdo->prepare("SELECT id, password FROM `admin_users` WHERE `username` = 'admin'");
    $stmt->execute();
    $admin_row = $stmt->fetch();
    if (!$admin_row) {
        $hashed_pwd = password_hash('AdminSecure2026!', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO `admin_users` (`username`, `password`) VALUES ('admin', ?)");
        $insert->execute([$hashed_pwd]);
    } else {
        // If password is not hashed yet, hash it
        if (substr($admin_row['password'], 0, 4) !== '$2y$') {
            $hashed_pwd = password_hash($admin_row['password'], PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE `admin_users` SET `password` = ? WHERE `username` = 'admin'");
            $update->execute([$hashed_pwd]);
        }
    }
    
    // 2. Create customers table (NO UNIQUE constraint on email_address)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `full_name` TEXT NOT NULL,
        `email_address` TEXT NOT NULL,
        `amount` REAL DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Run migration if customers table has an existing UNIQUE index on email_address in SQLite
    try {
        $hasUniqueEmail = false;
        $indexes = $pdo->query("PRAGMA index_list('customers')")->fetchAll();
        foreach ($indexes as $index) {
            if ($index['unique'] == 1) {
                $fields = $pdo->query("PRAGMA index_info('{$index['name']}')")->fetchAll();
                foreach ($fields as $field) {
                    if ($field['name'] === 'email_address') {
                        $hasUniqueEmail = true;
                    }
                }
            }
        }
        if ($hasUniqueEmail) {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
            $pdo->exec("ALTER TABLE `customers` RENAME TO `customers_old`;");
            $pdo->exec("CREATE TABLE `customers` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `full_name` TEXT NOT NULL,
                `email_address` TEXT NOT NULL,
                `amount` REAL DEFAULT 0.00,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            );");
            $pdo->exec("INSERT INTO `customers` (`id`, `full_name`, `email_address`, `amount`, `created_at`) 
                        SELECT `id`, `full_name`, `email_address`, 0.00, `created_at` FROM `customers_old`;");
            $pdo->exec("DROP TABLE `customers_old`;");
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }
    } catch (Exception $e) {
        error_log("Customers UNIQUE migration warning: " . $e->getMessage());
    }

    // Ensure amount column exists (if table was already created without it)
    try {
        $custColCheck = $pdo->query("PRAGMA table_info(customers)")->fetchAll();
        $hasAmount = false;
        foreach ($custColCheck as $col) {
            if ($col['name'] === 'amount') {
                $hasAmount = true;
                break;
            }
        }
        if (!$hasAmount) {
            $pdo->exec("ALTER TABLE `customers` ADD COLUMN `amount` REAL DEFAULT 0.00");
            // Populate default mock amounts for existing customer IDs
            $pdo->exec("UPDATE `customers` SET `amount` = 1250.00 WHERE `id` = 1");
            $pdo->exec("UPDATE `customers` SET `amount` = 850.00 WHERE `id` = 2");
            $pdo->exec("UPDATE `customers` SET `amount` = 950.00 WHERE `id` = 3");
            $pdo->exec("UPDATE `customers` SET `amount` = 450.00 WHERE `id` = 5");
            $pdo->exec("UPDATE `customers` SET `amount` = 1100.00 WHERE `id` = 6");
            $pdo->exec("UPDATE `customers` SET `amount` = 300.00 WHERE `id` = 7");
            $pdo->exec("UPDATE `customers` SET `amount` = 650.00 WHERE `id` = 9");
        }
    } catch (Exception $e) {
        error_log("Customers amount column migration error: " . $e->getMessage());
    }
    
    // 3. Create products table (with detail_description column)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `name` TEXT NOT NULL UNIQUE,
        `description` TEXT NOT NULL,
        `detail_description` TEXT,
        `category` TEXT NOT NULL,
        `tags` TEXT NOT NULL,
        `integration` TEXT NOT NULL,
        `deployment` TEXT NOT NULL,
        `release_date` TEXT NOT NULL,
        `rating` REAL DEFAULT 5.00,
        `review_count` INTEGER DEFAULT 0,
        `image_path` TEXT NOT NULL,
        `basic_price` REAL NOT NULL,
        `standard_price` REAL NOT NULL,
        `custom_price` TEXT NOT NULL DEFAULT 'Custom',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if detail_description column exists in existing database (if any) and add it if not
    $colCheck = $pdo->query("PRAGMA table_info(products)")->fetchAll();
    $hasDetailDesc = false;
    foreach ($colCheck as $col) {
        if ($col['name'] === 'detail_description') {
            $hasDetailDesc = true;
            break;
        }
    }
    if (!$hasDetailDesc) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `detail_description` TEXT");
    }
    
    // Seed default products if empty (or reset them to populate the detail_description field and varying prices)
    $prodCount = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `detail_description` IS NOT NULL")->fetchColumn();
    // We will clear and reseed products if we want to ensure prices are updated
    $pdo->exec("DELETE FROM `products`"); // Clear to reseed with full customized pricing and descriptions
    
    $default_prods = [
        [
            'name' => 'OmniMetrics AI',
            'description' => 'Advanced predictive analytics dashboard that automatically identifies workflow bottlenecks across your enterprise architecture with real-time insights.',
            'detail_description' => 'OmniMetrics AI provides an end-to-end telemetry and observability platform built specifically for high-throughput enterprise systems. Featuring dynamic log analysis, memory leak prediction models, and live execution tracing, it integrates seamlessly with major cloud service providers (AWS, Azure, GCP) and on-premise application servers. Its advanced proprietary anomaly detection engine parses millions of system queries per minute to highlight architectural choke points, queue backlogs, and network latency anomalies, presenting them in a beautiful, glassmorphic analytics dashboard. It helps teams proactively optimize cloud resources, reduce operational costs, and identify code bottlenecks before they affect end users.',
            'category' => 'new analytics',
            'tags' => 'AI-Powered,Predictive,Enterprise',
            'integration' => 'M365, Salesforce, SAP',
            'deployment' => 'Cloud / On-Premise',
            'release_date' => 'October 2023',
            'rating' => 4.90,
            'review_count' => 6,
            'image_path' => 'images/dashboard.png',
            'basic_price' => 299.00,
            'standard_price' => 799.00,
            'custom_price' => 'Custom'
        ],
        [
            'name' => 'Nexus Assist Pro',
            'description' => 'Our flagship virtual assistant deeply integrated with M365 and Google Workspace to automate routine employee inquiries around the clock.',
            'detail_description' => 'Nexus Assist Pro is a state-of-the-art conversational AI agent engineered for corporate workspaces. It connects securely to internal databases, knowledge bases, and document stores to provide employees with context-aware, cited answers to standard IT, HR, and facilities questions. Leveraging secure Retrieval-Augmented Generation (RAG) models, Nexus ensures corporate data compliance (GDPR, HIPAA, SOC2) and runs on a secure, sandboxed environment. Includes pre-built integrations with Microsoft Teams, Slack, Outlook, and Google Chat. It enables businesses to reduce employee support ticket resolution times by up to 50% and dramatically improve digital employee experience metrics.',
            'category' => 'new assistant',
            'tags' => 'Virtual Assistant,M365,Google WS',
            'integration' => 'M365, Google Workspace',
            'deployment' => 'Cloud / On-Premise',
            'release_date' => 'October 2023',
            'rating' => 5.00,
            'review_count' => 4,
            'image_path' => 'images/hero.png',
            'basic_price' => 349.00,
            'standard_price' => 899.00,
            'custom_price' => 'Custom'
        ],
        [
            'name' => 'LogicBuilder 3.0',
            'description' => 'Rapid prototyping solution for IT departments to visually construct and deploy custom AI logic trees without writing a single line of code.',
            'detail_description' => 'LogicBuilder 3.0 is a drag-and-drop no-code development studio designed for enterprise IT administrators. Build custom reasoning chains, automated data routing rules, and multi-model AI workflows on a high-fidelity visual WebGL canvas. It enables rapid prototyping of business logic, auto-generates secure deployment configs (Docker, Kubernetes), and supports live debugging. Easily connect 200+ external API actions, database endpoints, and webhook triggers with zero coding required. This platform empowers rapid application prototyping and lets non-developers safely build custom AI logic chains in minutes.',
            'category' => 'new assistant analytics',
            'tags' => 'No-Code,Workflow,Automation',
            'integration' => 'Zero-Code Setup',
            'deployment' => '200+ integrations',
            'release_date' => 'September 2023',
            'rating' => 4.70,
            'review_count' => 8,
            'image_path' => 'images/workflow.png',
            'basic_price' => 199.00,
            'standard_price' => 599.00,
            'custom_price' => 'Custom'
        ],
        [
            'name' => 'ComplianceBot AI',
            'description' => 'Real-time policy checking and automated compliance auditing assistant designed for highly regulated B2B financial and healthcare operations.',
            'detail_description' => 'ComplianceBot AI automates regulatory oversight by conducting real-time policy checks across all system transactions and communications. Specially tuned for fintech, healthcare, and insurance operations, it continuously parses activity logs to detect policy deviations, active directory anomalies, and unauthorized data transfers. It auto-generates comprehensive compliance reports suitable for ISO 27001, SOC2, HIPAA, and GDPR audits, reducing human oversight costs by up to 60%. It ensures continuous compliance posture and sends instant alerts when high-risk policy violations occur.',
            'category' => 'new assistant',
            'tags' => 'Compliance,Auditing,Fintech / Health',
            'integration' => 'Workday, ServiceNow, DBs',
            'deployment' => 'ISO 27001, HIPAA, GDPR',
            'release_date' => 'November 2024',
            'rating' => 4.80,
            'review_count' => 10,
            'image_path' => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=600',
            'basic_price' => 399.00,
            'standard_price' => 999.00,
            'custom_price' => 'Custom'
        ],
        [
            'name' => 'AutoRespond Agent',
            'description' => 'Autonomous corporate responder that parses and replies to thousands of common customer queries via email/tickets with semantic CRM integrations.',
            'detail_description' => 'AutoRespond Agent is an autonomous ticket and email resolution system designed to supercharge customer support teams. Powered by semantic intent classification and CRM records mapping, it parses inbound support messages, retrieves client histories, and compiles precise, personalized drafts or auto-replies. Integrates natively with Outlook, Gmail, Salesforce Service Cloud, and Zendesk, achieving a 98.4% auto-resolution rate on common transactional queries. This enables customer support agents to focus on complex queries while the AI automatically resolves routine support tickets.',
            'category' => 'new assistant',
            'tags' => 'Auto-Responder,Outlook / Gmail,CRM Sync',
            'integration' => 'Outlook, Gmail, Salesforce',
            'deployment' => '98.4% auto-resolve',
            'release_date' => 'December 2024',
            'rating' => 4.90,
            'review_count' => 15,
            'image_path' => 'https://images.unsplash.com/photo-1557200134-90327ee9fafa?q=80&w=600',
            'basic_price' => 249.00,
            'standard_price' => 699.00,
            'custom_price' => 'Custom'
        ],
        [
            'name' => 'DataSync Core AI',
            'description' => 'Enterprise semantic database migration and synchronization engine that maps legacy SQL systems with real-time replication and zero downtime.',
            'detail_description' => 'DataSync Core AI provides zero-downtime database replication, schema translation, and real-time synchronization. Engineered for legacy database modernization, it maps standard SQL relations, cleanses dirty records, and translates schemas dynamically between MySQL, MSSQL, Oracle, and SQLite databases. Features AES-256 stateful transit encryption and a visual sync health console. It is the perfect tool for executing database migrations without interrupting live services or risking transaction data loss.',
            'category' => 'legacy analytics',
            'tags' => 'Database,Data Sync,Stable Core',
            'integration' => 'MySQL, MSSQL, Oracle',
            'deployment' => 'AES-256 encrypted',
            'release_date' => 'January 2022',
            'rating' => 4.80,
            'review_count' => 6,
            'image_path' => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?q=80&w=600',
            'basic_price' => 149.00,
            'standard_price' => 499.00,
            'custom_price' => 'Custom'
        ],
        [
            'name' => 'OmniSearch Enterprise',
            'description' => 'Cognitive enterprise search tool that indexes all internal databases, wikis, and cloud files to provide instant, secure answers with cited sources.',
            'detail_description' => 'OmniSearch Enterprise is an AI-powered cognitive search tool that indexes and crawls all decentralized corporate files, messaging histories, wikis, and document vaults. Utilizing secure semantic search embeddings, it respects role-based access privileges (Active Directory) to deliver instant, secure answers with inline source citations. Employees can query all corporate knowledge in natural language, finding files in seconds. This eliminates wasted hours spent manually digging through folders and enhances corporate productivity.',
            'category' => 'new assistant',
            'tags' => 'Cognitive Search,Secure RAG,Multi-Source',
            'integration' => 'Drive, Sharepoint, Slack',
            'deployment' => 'Active Directory, RAG',
            'release_date' => 'March 2024',
            'rating' => 5.00,
            'review_count' => 8,
            'image_path' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?q=80&w=600',
            'basic_price' => 449.00,
            'standard_price' => 1199.00,
            'custom_price' => 'Custom'
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO `products` 
        (`name`, `description`, `detail_description`, `category`, `tags`, `integration`, `deployment`, `release_date`, `rating`, `review_count`, `image_path`, `basic_price`, `standard_price`, `custom_price`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
    foreach ($default_prods as $p) {
        $stmt->execute([
            $p['name'],
            $p['description'],
            $p['detail_description'],
            $p['category'],
            $p['tags'],
            $p['integration'],
            $p['deployment'],
            $p['release_date'],
            $p['rating'],
            $p['review_count'],
            $p['image_path'],
            $p['basic_price'],
            $p['standard_price'],
            $p['custom_price']
        ]);
    }

    // 4. Create customer_purchases table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_purchases` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `customer_id` INTEGER NOT NULL,
        `product_name` TEXT NOT NULL,
        `purchase_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
    )");

    // 5. Create customer_inquiries table (with CRM columns)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_inquiries` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `customer_id` INTEGER DEFAULT NULL,
        `full_name` TEXT NOT NULL,
        `email_address` TEXT NOT NULL,
        `phone_number` TEXT DEFAULT NULL,
        `company_name` TEXT NOT NULL DEFAULT '',
        `country` TEXT NOT NULL DEFAULT '',
        `job_title` TEXT NOT NULL DEFAULT '',
        `request_type` TEXT NOT NULL DEFAULT 'Inquiry',
        `product_name` TEXT NOT NULL DEFAULT '',
        `package_name` TEXT NOT NULL DEFAULT '',
        `deposit_amount` REAL DEFAULT 0.00,
        `payment_status` TEXT NOT NULL DEFAULT 'Free',
        `custom_wishes` TEXT DEFAULT NULL,
        `inquiry_details` TEXT NOT NULL DEFAULT '',
        `deal_status` TEXT NOT NULL DEFAULT 'New Lead',
        `total_received` REAL DEFAULT 0.00,
        `deal_value` REAL DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
    )");

    // Migrate existing inquiries table for deal_status, total_received, and deal_value
    $inqColCheck = $pdo->query("PRAGMA table_info(customer_inquiries)")->fetchAll();
    $hasDealStatus = false;
    $hasTotalReceived = false;
    $hasDealValue = false;
    foreach ($inqColCheck as $col) {
        if ($col['name'] === 'deal_status') $hasDealStatus = true;
        if ($col['name'] === 'total_received') $hasTotalReceived = true;
        if ($col['name'] === 'deal_value') $hasDealValue = true;
    }
    if (!$hasDealStatus) {
        $pdo->exec("ALTER TABLE `customer_inquiries` ADD COLUMN `deal_status` TEXT NOT NULL DEFAULT 'New Lead'");
    }
    if (!$hasTotalReceived) {
        $pdo->exec("ALTER TABLE `customer_inquiries` ADD COLUMN `total_received` REAL DEFAULT 0.00");
    }
    if (!$hasDealValue) {
        $pdo->exec("ALTER TABLE `customer_inquiries` ADD COLUMN `deal_value` REAL DEFAULT 0.00");
    }

    // 5.5 Create customer_conversations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_conversations` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `inquiry_id` INTEGER NOT NULL,
        `sender` TEXT NOT NULL,
        `message` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`inquiry_id`) REFERENCES `customer_inquiries`(`id`) ON DELETE CASCADE
    )");

    // 6. Create customer_reviews table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_reviews` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `reviewer_name` TEXT NOT NULL,
        `reviewer_role` TEXT NOT NULL,
        `email_address` TEXT NOT NULL DEFAULT '',
        `rating` INTEGER NOT NULL,
        `product_name` TEXT NOT NULL,
        `review_date` TEXT NOT NULL,
        `review_text` TEXT NOT NULL,
        `reviewer_img` TEXT NOT NULL,
        `is_verified` INTEGER NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. Create events table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `events` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `title` TEXT NOT NULL,
        `badge_text` TEXT NOT NULL,
        `badge_class` TEXT NOT NULL,
        `description` TEXT NOT NULL,
        `event_date` TEXT NOT NULL,
        `image_path` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 7.5 Create event_registrations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `event_registrations` (
        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
        `full_name` TEXT NOT NULL,
        `email_address` TEXT NOT NULL,
        `company_name` TEXT NOT NULL,
        `event_title` TEXT NOT NULL,
        `registration_date` DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default registrations if empty
    $regCount = $pdo->query("SELECT COUNT(*) FROM `event_registrations`")->fetchColumn();
    if ($regCount == 0) {
        $pdo->exec("INSERT INTO `event_registrations` (`full_name`, `email_address`, `company_name`, `event_title`) VALUES
            ('Sarah Jenkins', 's.jenkins@globallogistics.co.uk', 'Global Logistics Corp', 'Sunderland Tech Summit 2026'),
            ('David Chen', 'd.chen@fintechinnovations.com', 'FinTech Innovations', 'Scaling HR Virtual Assistants')
        ");
    }

    // Seed default events if empty
    $evtCount = $pdo->query("SELECT COUNT(*) FROM `events`")->fetchColumn();
    if ($evtCount == 0) {
        $default_events = [
            [
                'Sunderland Tech Summit 2026',
                'Live Summit',
                'bg-info text-dark',
                'Join our executive and engineering teams as we present our B2B integration roadmaps live at the Innovation Hub.',
                'October 15–16, 2026',
                'https://images.unsplash.com/photo-1540575467063-178a50c2df87?q=80&w=600'
            ],
            [
                'Scaling HR Virtual Assistants',
                'Webinar',
                'bg-success',
                'A technical webinar outlining how to optimize employee workflows and reduce ticket response times by 50%.',
                'November 05, 2026',
                'https://images.unsplash.com/photo-1515187029135-18ee286d815b?q=80&w=600'
            ]
        ];
        $e_stmt = $pdo->prepare("INSERT INTO `events` (`title`, `badge_text`, `badge_class`, `description`, `event_date`, `image_path`) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($default_events as $evt) {
            $e_stmt->execute($evt);
        }
    }

    // Seed default customers if empty
    $custCount = $pdo->query("SELECT COUNT(*) FROM `customers`")->fetchColumn();
    if ($custCount == 0) {
        $default_customers = [
            ['Sarah Jenkins', 's.jenkins@globallogistics.co.uk', 1250.00],
            ['David Chen', 'd.chen@fintechinnovations.com', 850.00],
            ['Elena Rodriguez', 'e.rodriguez@healthtech.co.uk', 950.00],
            ['James Whitmore', 'j.whitmore@healthlink.org', 0.00],
            ['Priya Sharma', 'p.sharma@fintechcorp.com', 450.00],
            ['Thomas Mueller', 't.mueller@autotech.de', 1100.00],
            ['Alexander Wright', 'a.wright@nexustech.io', 300.00],
            ['Emily Watson', 'e.watson@complyhealth.com', 0.00],
            ['Marcus Vance', 'm.vance@retailcore.com', 650.00]
        ];
        $c_stmt = $pdo->prepare("INSERT INTO `customers` (`full_name`, `email_address`, `amount`) VALUES (?, ?, ?)");
        foreach ($default_customers as $c) {
            $c_stmt->execute($c);
        }
    }

    // Seed default purchases if empty
    $purchCount = $pdo->query("SELECT COUNT(*) FROM `customer_purchases`")->fetchColumn();
    if ($purchCount == 0) {
        $default_purchases = [
            [1, 'Nexus Assist Pro'],
            [2, 'LogicBuilder 3.0'],
            [3, 'OmniMetrics AI'],
            [5, 'DataSync Core AI']
        ];
        $p_stmt = $pdo->prepare("INSERT INTO `customer_purchases` (`customer_id`, `product_name`) VALUES (?, ?)");
        foreach ($default_purchases as $p) {
            $p_stmt->execute($p);
        }
    }

    // Seed default inquiries if empty
    $inqCount = $pdo->query("SELECT COUNT(*) FROM `customer_inquiries`")->fetchColumn();
    if ($inqCount == 0) {
        $default_inquiries = [
            [1, 'Sarah Jenkins', 's.jenkins@globallogistics.co.uk', '+44 191 123 4567', 'Global Logistics Corp', 'United Kingdom', 'CTO', 'Demo', 'Nexus Assist Pro', 'Standard Package', 39.95, 'Paid', 'We would like to test the M365 email integration.', 'Demo Active', 39.95, 899.00],
            [2, 'David Chen', 'd.chen@fintechinnovations.com', '+1 212 987 6543', 'FinTech Innovations', 'United States', 'IT Director', 'Inquiry', 'LogicBuilder 3.0', 'Basic Package', 0.00, 'Free', 'Can we run this fully on-premise?', 'Proposal Sent', 0.00, 199.00],
            [3, 'Elena Rodriguez', 'e.rodriguez@healthtech.co.uk', '+44 191 765 4321', 'HealthTech UK', 'United Kingdom', 'VP Operations', 'Demo', 'OmniMetrics AI', 'Standard Package', 39.95, 'Paid', 'Looking for real-time memory leak tracing.', 'Sold', 799.00, 799.00],
            [5, 'Priya Sharma', 'p.sharma@fintechcorp.com', '+91 22 9999 8888', 'FinTech Corp', 'India', 'Chief Data Officer', 'Inquiry', 'DataSync Core AI', 'Custom Plan', 0.00, 'Free', 'Need custom replication rules for MSSQL.', 'New Lead', 0.00, 0.00],
            [7, 'Alexander Wright', 'a.wright@nexustech.io', '+44 207 946 0958', 'NexusTech', 'United Kingdom', 'Systems Lead', 'Demo', 'Nexus Assist Pro', 'Basic Package', 14.95, 'Pending', 'Interested in HR virtual assistant.', 'Paid Demo', 14.95, 349.00],
            [8, 'Emily Watson', 'e.watson@complyhealth.com', '+1 617 555 0199', 'ComplyHealth', 'United States', 'Security Lead', 'Inquiry', 'ComplianceBot AI', 'Standard Package', 0.00, 'Free', 'Does this support HIPAA audit logs out of the box?', 'New Lead', 0.00, 0.00],
            [9, 'Marcus Vance', 'm.vance@retailcore.com', '+1 415 555 2671', 'RetailCore', 'United States', 'Ops VP', 'Demo', 'AutoRespond Agent', 'Standard Package', 39.95, 'Paid', 'Need to test Salesforce integration.', 'Sold', 699.00, 699.00]
        ];
        $i_stmt = $pdo->prepare("INSERT INTO `customer_inquiries` 
            (`customer_id`, `full_name`, `email_address`, `phone_number`, `company_name`, `country`, `job_title`, `request_type`, `product_name`, `package_name`, `deposit_amount`, `payment_status`, `custom_wishes`, `deal_status`, `total_received`, `deal_value`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($default_inquiries as $i) {
            $i_stmt->execute($i);
        }
    }

    // Seed default reviews if empty
    $revCount = $pdo->query("SELECT COUNT(*) FROM `customer_reviews`")->fetchColumn();
    if ($revCount == 0) {
        $default_reviews = [
            // OmniMetrics AI (6 reviews)
            ['Sarah Jenkins', 'CTO, Global Logistics Corp', 's.jenkins@globallogistics.co.uk', 5, 'OmniMetrics AI', 'Oct 2023', 'The predictive analytics from OmniMetrics pinpointed operational bottlenecks we didn\'t even know existed. A truly phenomenal piece of software for any enterprise.', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=100', 0],
            ['Thomas Mueller', 'CTO, AutoTech GmbH', 't.mueller@autotech.de', 5, 'OmniMetrics AI', 'Nov 2023', 'OmniMetrics AI transformed how we monitor our supply chain operations. The ROI was evident within the first quarter of deployment. Highly recommended.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['John Doe', 'VP Infrastructure, CloudSync', 'j.doe@cloudsync.io', 4, 'OmniMetrics AI', 'Dec 2023', 'Great predictive alerts and metrics dashboard, but on-premise configuration takes some dedicated setup time.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Alice Smith', 'Head of IT, TechCore', 'a.smith@techcore.com', 5, 'OmniMetrics AI', 'Jan 2024', 'Excellent visualization dashboard, we love the dark mode and real-time alert latency widgets.', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=100', 0],
            ['Bob Jones', 'Lead Engineer, DataGrid', 'b.jones@datagrid.co.uk', 3, 'OmniMetrics AI', 'Feb 2024', 'Good analytical data, but the alert notifications are a bit too frequent. Hopefully custom triggers can be adjusted in next release.', 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?q=80&w=100', 0],
            ['Charlie Brown', 'VP Devops, SysLogic', 'c.brown@syslogic.net', 4, 'OmniMetrics AI', 'Mar 2024', 'Very useful telemetry tracing. Support helped us integrate with our custom AWS cluster easily.', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=100', 0],

            // Nexus Assist Pro (4 reviews)
            ['Sarah Jenkins', 'CTO, Global Logistics Corp', 's.jenkins@globallogistics.co.uk', 5, 'Nexus Assist Pro', 'Oct 2023', 'The deployment of Nexus Assist Pro was seamless. AI-Solution\'s team guided us every step of the way, and the results have drastically exceeded our expectations with a 45% reduction in IT tickets.', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=100', 1],
            ['James Whitmore', 'IT Operations Director, HealthLink', 'j.whitmore@healthlink.org', 5, 'Nexus Assist Pro', 'Mar 2022', 'An absolute workhorse. The customer support is top-notch and the documentation is comprehensive.', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', 0],
            ['Michael Green', 'HR Director, StaffFlow', 'm.green@staffflow.com', 4, 'Nexus Assist Pro', 'May 2024', 'Helpful HR automation. Saves our agents several hours daily by resolving basic employee payroll queries.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['Sophia Carter', 'CX Manager, RetailForce', 's.carter@retailforce.com', 3, 'Nexus Assist Pro', 'Jun 2024', 'Nice glassmorphic interface, but needs better natural language understanding for complex, multi-sentence queries.', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?q=80&w=100', 0],

            // LogicBuilder 3.0 (8 reviews)
            ['David Chen', 'IT Director, FinTech Innovations', 'd.chen@fintechinnovations.com', 5, 'LogicBuilder 3.0', 'Sep 2023', 'LogicBuilder allowed our small IT team to act like a massive development department. We deployed 5 custom AI workflows in under a month. Absolutely game-changing.', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=100', 1],
            ['Liam Neeson', 'Lead Architect, Securitas', 'l.neeson@securitas.com', 4, 'LogicBuilder 3.0', 'Nov 2023', 'We built 3 custom logic trees within a week. Simple visual canvas, highly interactive layout.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Noah Williams', 'IT Lead, Intellect', 'n.williams@intellect.co.uk', 5, 'LogicBuilder 3.0', 'Jan 2024', 'Outstanding WebGL performance. Drag-and-drop workflow canvas works flawlessly under high loads.', 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?q=80&w=100', 0],
            ['Oliver Davies', 'VP Engineering, TechStack', 'o.davies@techstack.io', 5, 'LogicBuilder 3.0', 'Feb 2024', 'Perfect for fast prototyping. The Docker configuration export is a huge time-saver for our devops team.', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=100', 0],
            ['Emma Watson', 'Systems Analyst, BioHealth', 'e.watson@biohealth.org', 3, 'LogicBuilder 3.0', 'Mar 2024', 'Very powerful workflow tool, but requires a steep learning curve for non-technical administrators.', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=100', 0],
            ['Ava Taylor', 'Product Owner, AppVantage', 'a.taylor@appvantage.com', 4, 'LogicBuilder 3.0', 'May 2024', 'Excellent integration with Slack webhooks and custom API endpoints. Saves us weeks of custom coding.', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?q=80&w=100', 0],
            ['Mia Thomas', 'Lead Developer, WebCore', 'm.thomas@webcore.net', 4, 'LogicBuilder 3.0', 'Jun 2024', 'Saves a lot of time on visual script setups. Looking forward to the 4.0 update with more AI node templates.', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=100', 0],
            ['Jacob Evans', 'VP Technology, CoreBank', 'j.evans@corebank.com', 5, 'LogicBuilder 3.0', 'Jun 2024', 'A must-have tool for rapid enterprise workflow prototyping and database integration.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100', 0],

            // ComplianceBot AI (10 reviews)
            ['Daniel Craig', 'SecOps Director, FinanceGroup', 'd.craig@financegroup.com', 5, 'ComplianceBot AI', 'Feb 2024', 'ComplianceBot AI automated our SOC2 audit trail completely. Highly secure on-premise execution.', 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?q=80&w=100', 0],
            ['Ethan Hunt', 'Compliance Lead, HealthSafe', 'e.hunt@healthsafe.org', 5, 'ComplianceBot AI', 'Mar 2024', 'Flawless real-time policy checking for our healthcare databases. Fully HIPAA compliant.', 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?q=80&w=100', 0],
            ['Grace Hopper', 'IT Auditor, GovSys', 'g.hopper@govsys.gov', 4, 'ComplianceBot AI', 'Apr 2024', 'Great policy auditing templates. Support was very prompt with our custom GDPR layout questions.', 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?q=80&w=100', 0],
            ['Lucas Gray', 'Security Analyst, BitPay', 'l.gray@bitpay.com', 4, 'ComplianceBot AI', 'May 2024', 'Saves our compliance audit team about 20 hours of manual Active Directory log checking per week.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['Harper Mason', 'Risk Officer, InsureCo', 'h.mason@insureco.com', 5, 'ComplianceBot AI', 'May 2024', 'Highly recommended for any regulated B2B financial services setup.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100', 0],
            ['Evelyn Rose', 'CTO, MedData', 'e.rose@meddata.co.uk', 3, 'ComplianceBot AI', 'Jun 2024', 'Good compliance checker, but automated PDF report generation could be formatted better.', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=100', 0],
            ['Jack Ryan', 'Security Engineer, FederalIT', 'j.ryan@federalit.gov', 4, 'ComplianceBot AI', 'Jun 2024', 'Helps us stay compliant with active directory policies and logs anomalies instantly.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Henry Ford', 'COO, IndustryLog', 'h.ford@industrylog.com', 5, 'ComplianceBot AI', 'Jun 2024', 'Superb software. Setup was done in under an hour and automated triggers work perfectly.', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', 0],
            ['Sebastian Cole', 'VP Risk, AlphaWealth', 's.cole@alphawealth.com', 4, 'ComplianceBot AI', 'Jun 2024', 'Very clean dashboard for viewing active active directory compliance levels.', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=100', 0],
            ['Madison Lane', 'IT Auditor, HealthLink', 'm.lane@healthlink.org', 4, 'ComplianceBot AI', 'Jun 2024', 'Provides clear, immediate warnings when active directory policies are breached.', 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?q=80&w=100', 0],

            // AutoRespond Agent (15 reviews)
            ['George Clooney', 'Director, SupportDesk', 'g.clooney@supportdesk.com', 5, 'AutoRespond Agent', 'Jan 2024', 'Natively syncs with Salesforce. Achieved a 98% auto-resolution rate on routine enquiries!', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Robert Downey', 'VP Operations, HelpFlow', 'r.downey@helpflow.com', 5, 'AutoRespond Agent', 'Feb 2024', 'Amazing email automation tool. Has saved us thousands in support agent costs since rollout.', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', 0],
            ['Chris Evans', 'Support Lead, TechHQ', 'c.evans@techhq.net', 4, 'AutoRespond Agent', 'Mar 2024', 'Good CRM integration. Parses customer histories accurately to formulate replies.', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=100', 0],
            ['Scarlett Johansson', 'CX Specialist, CoreRetail', 's.johansson@coreretail.com', 5, 'AutoRespond Agent', 'Apr 2024', 'Very smart intent classification. Fits our customer support ticket workflows perfectly.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100', 0],
            ['Mark Ruffalo', 'Lead Developer, HelpGrid', 'm.ruffalo@helpgrid.org', 3, 'AutoRespond Agent', 'May 2024', 'Good semantic responder, but sometimes misclassifies complex technical support tickets.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['Jeremy Renner', 'IT Operations, GlobalLog', 'j.renner@globallog.com', 4, 'AutoRespond Agent', 'May 2024', 'Very reliable autonomous responder for standard billing and password reset queries.', 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?q=80&w=100', 0],
            ['Paul Rudd', 'Support Manager, WebSoft', 'p.rudd@websoft.io', 5, 'AutoRespond Agent', 'Jun 2024', 'Setup was straightforward. Customer experience has improved significantly with instant replies.', 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?q=80&w=100', 0],
            ['Chris Hemsworth', 'VP Support, CloudCorp', 'c.hemsworth@cloudcorp.com', 4, 'AutoRespond Agent', 'Jun 2024', 'Excellent for ticket drafting. Integrates well with Outlook and Gmail SMTP protocols.', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=100', 0],
            ['Tom Holland', 'Lead Engineer, AppFlow', 't.holland@appflow.co.uk', 4, 'AutoRespond Agent', 'Jun 2024', 'Saves our agents from repetitive queries. Extremely customizable prompt templates.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100', 0],
            ['Brie Larson', 'CX Director, GrowthCo', 'b.larson@growthco.net', 5, 'AutoRespond Agent', 'Jun 2024', 'Highly responsive support team. The RAG grounding database works reliably.', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=100', 0],
            ['Chadwick Boseman', 'Support Lead, SwiftDesk', 'c.boseman@swiftdesk.com', 5, 'AutoRespond Agent', 'Jun 2024', 'Superb performance. Handles high concurrency of incoming support emails without lagging.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Elizabeth Olsen', 'CTO, DataQuery', 'e.olsen@dataquery.io', 3, 'AutoRespond Agent', 'Jun 2024', 'A bit tricky to configure custom prompt replies, but works well once correctly mapped.', 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?q=80&w=100', 0],
            ['Benedict Cumberbatch', 'VP CX, RetailLink', 'b.cumberbatch@retaillink.com', 4, 'AutoRespond Agent', 'Jun 2024', 'Outstanding semantic mapping of tickets. Reduces agent reply backlogs effectively.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['Tom Hiddleston', 'Manager, ServiceFlow', 't.hiddleston@serviceflow.com', 5, 'AutoRespond Agent', 'Jun 2024', 'AutoRespond Agent has transformed our client support workflows completely.', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=100', 0],
            ['Paul Bettany', 'Ops Manager, NetSys', 'p.bettany@netsys.net', 4, 'AutoRespond Agent', 'Jun 2024', 'Very robust, stable autonomous response loops with clean ticket tagging.', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', 0],

            // DataSync Core AI (6 reviews)
            ['Priya Sharma', 'Chief Data Officer, FinTech Corp', 'p.sharma@fintechcorp.com', 5, 'DataSync Core AI', 'Jan 2022', 'Flawless database migration utility. The standard SQL engine is extremely stable. We still rely on it for nightly syncs and it has never let us down.', 'https://images.unsplash.com/photo-1551836022-deb4988cc6c0?q=80&w=100', 1],
            ['Samuel Jackson', 'DBA Lead, SafeData', 's.jackson@safedata.com', 4, 'DataSync Core AI', 'Feb 2024', 'Replication has been running for months with zero downtime or transaction lag.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['Zoe Saldana', 'SecOps Lead, SecureCloud', 'z.saldana@securecloud.io', 5, 'DataSync Core AI', 'Mar 2024', 'AES-256 stateful transit encryption gives us full security confidence during migrations.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100', 0],
            ['Chris Pratt', 'Lead Architect, DataWay', 'c.pratt@dataway.net', 5, 'DataSync Core AI', 'Apr 2024', 'Simple schema translation. Solved our MSSQL to PostgreSQL database migration hurdles.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Vin Diesel', 'COO, TransportLog', 'v.diesel@transportlog.de', 3, 'DataSync Core AI', 'May 2024', 'Stable database sync engine, but lacks a native Web UI for viewing transfer logs.', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', 0],
            ['Bradley Cooper', 'Systems Administrator, Apex', 'b.cooper@apex.com', 4, 'DataSync Core AI', 'Jun 2024', 'Excellent migration script utility. Technical support is top-notch and responsive.', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=100', 0],

            // OmniSearch Enterprise (8 reviews)
            ['Christian Bale', 'CTO, SearchLogic', 'c.bale@searchlogic.com', 5, 'OmniSearch Enterprise', 'Mar 2024', 'Cognitive search works wonders across our decentralized internal document vaults.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0],
            ['Anne Hathaway', 'Lead Compliance, CorpIntel', 'a.hathaway@corpintel.com', 5, 'OmniSearch Enterprise', 'Apr 2024', 'Respects our Active Directory role-based access privileges perfectly. Incredibly secure RAG.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100', 0],
            ['Gary Oldman', 'VP Operations, GlobalNet', 'g.oldman@globalnet.co.uk', 4, 'OmniSearch Enterprise', 'May 2024', 'Incredible RAG citations. No more searching through thousands of files manually.', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=100', 0],
            ['Tom Hardy', 'IT Lead, IntelSearch', 't.hardy@intelsearch.net', 5, 'OmniSearch Enterprise', 'May 2024', 'Finds files in seconds. Absolute lifesaver for our engineering teams and research labs.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100', 0],
            ['Marion Cotillard', 'CX Manager, EuroSoft', 'm.cotillard@eurosoft.fr', 3, 'OmniSearch Enterprise', 'Jun 2024', 'Good search results, but initial indexing took 12 hours for our shared corporate drive.', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=100', 0],
            ['Joseph Gordon', 'Product Lead, QueryApp', 'j.gordon@queryapp.com', 4, 'OmniSearch Enterprise', 'Jun 2024', 'A great cognitive search assistant. Very clean, intuitive user interface.', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=100', 0],
            ['Cillian Murphy', 'SecOps Lead, SafeBank', 'c.murphy@safebank.ch', 4, 'OmniSearch Enterprise', 'Jun 2024', 'Highly secure and accurate search engine. Citations are very helpful for audit verification.', 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?q=80&w=100', 0],
            ['Morgan Freeman', 'Chairman, GlobalCons', 'm.freeman@globalcons.com', 5, 'OmniSearch Enterprise', 'Jun 2024', 'The best RAG-based search tool on the market. Simplifies company training significantly.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100', 0]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO `customer_reviews` 
            (`reviewer_name`, `reviewer_role`, `email_address`, `rating`, `product_name`, `review_date`, `review_text`, `reviewer_img`, `is_verified`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        foreach ($default_reviews as $r) {
            $stmt->execute($r);
        }

        // Dynamically update product ratings and review counts to be 100% mathematically correct and consistent
        $pdo->exec("UPDATE `products` SET 
            `rating` = (SELECT ROUND(AVG(rating), 2) FROM `customer_reviews` WHERE `customer_reviews`.`product_name` = `products`.`name`),
            `review_count` = (SELECT COUNT(*) FROM `customer_reviews` WHERE `customer_reviews`.`product_name` = `products`.`name`)
        ");
    }

} catch (PDOException $e) {
    error_log("Database initialization error: " . $e->getMessage());
    die("Database Error: " . $e->getMessage());
}

/**
 * Helper to fetch database connection instance
 */
function getDbConnection() {
    global $pdo;
    return $pdo;
}
