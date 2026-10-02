<?php
session_start();
ob_start();

// ==========================================
// SYSTEM CONFIGURATION
// ==========================================
$storage_space = "bonette_portfolio_system.db";
$media_folder = "uploads/";
$family_folder = "family_photos/";
$activity_folder = "activity_photos/";
$portfolio_folder = "portfolio_photos/";
$profile_folder = "profile_photos/";

// Create all necessary folders
foreach ([$media_folder, $family_folder, $activity_folder, $portfolio_folder, $profile_folder] as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
}

// Theme Management
$current_theme = isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light' ? 'light' : 'dark';
if (isset($_GET['set_theme'])) {
    $new_theme = $_GET['set_theme'] === 'light' ? 'light' : 'dark';
    setcookie('theme', $new_theme, time() + (86400 * 365), '/');
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

try {
    $engine = new PDO("sqlite:" . $storage_space);
    $engine->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $engine->exec("PRAGMA journal_mode=WAL");
    $engine->exec("PRAGMA foreign_keys=ON");

    // Helper functions
    function columnExists($pdo, $table, $column) {
        $result = $pdo->query("PRAGMA table_info($table)");
        foreach ($result as $col) {
            if ($col['name'] === $column) return true;
        }
        return false;
    }

    function tableExists($pdo, $table) {
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        return $result->fetch() !== false;
    }

    // ==========================================
    // DATABASE TABLES CREATION
    // ==========================================
    
    // Family members table
    if (!tableExists($engine, 'family_members')) {
        $engine->exec("CREATE TABLE family_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            birth_order INTEGER,
            password TEXT NOT NULL,
            avatar TEXT DEFAULT 'default.png',
            can_upload INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert all 8 family members with birth order
        $family_data = [
            ['Jack', 'Father', null, 'jack2024'],
            ['Oliva', 'Mother', null, 'oliva2024'],
            ['Cedric', 'Brother', 1, 'cedric2024'],
            ['Bonette', 'Self', 2, 'bonette2024'],
            ['Mugisha', 'Brother', 3, 'mugisha2024'],
            ['Ruth', 'Sister', 4, 'ruth2024'],
            ['Reponce', 'Sister', 5, 'reponce2024'],
            ['Beryse', 'Sister', 6, 'beryse2024']
        ];
        
        $stmt = $engine->prepare("INSERT INTO family_members (name, role, birth_order, password) VALUES (?, ?, ?, ?)");
        foreach ($family_data as $member) {
            $stmt->execute([$member[0], $member[1], $member[2], password_hash($member[3], PASSWORD_DEFAULT)]);
        }
    }

    // Users table (for admin/developer access)
    if (!tableExists($engine, 'users')) {
        $engine->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            full_name TEXT,
            email TEXT,
            phone TEXT,
            date_of_birth TEXT,
            school TEXT,
            course TEXT,
            bio TEXT,
            skills TEXT,
            avatar TEXT DEFAULT 'default.png',
            profile_photo TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert Bonette as developer/admin
        $stmt = $engine->prepare("INSERT INTO users (username, password, role, full_name, email, phone, date_of_birth, school, course, bio, skills) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'bonette',
            password_hash('bonette2024', PASSWORD_DEFAULT),
            'developer',
            'IBYOYAKOZE Bonette',
            'bonette@developer.rw',
            '0792259699',
            '2008-10-15',
            'LYCEE SAINT ALEXAUNDRE SAULI DE MUHURA',
            'L4 Software Development',
            'Aspiring Software Engineer | Second child in a family of 8 | Passionate about coding and technology',
            'PHP, SQL, HTML, CSS, JavaScript, Python, Software Development, Web Design'
        ]);
    } else {
        // Add profile_photo column if missing
        if (!columnExists($engine, 'users', 'profile_photo')) {
            $engine->exec("ALTER TABLE users ADD COLUMN profile_photo TEXT");
        }
    }

    // Family chat messages table
    if (!tableExists($engine, 'family_chat')) {
        $engine->exec("CREATE TABLE family_chat (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            sender_name TEXT,
            sender_role TEXT,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Gallery table
    if (!tableExists($engine, 'gallery')) {
        $engine->exec("CREATE TABLE gallery (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_path TEXT NOT NULL,
            caption TEXT,
            category TEXT DEFAULT 'general',
            uploaded_by TEXT,
            uploaded_by_role TEXT,
            uploaded_by_type TEXT DEFAULT 'admin',
            downloads INTEGER DEFAULT 0,
            likes INTEGER DEFAULT 0,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        // Add missing columns if needed
        if (!columnExists($engine, 'gallery', 'uploaded_by_role')) {
            $engine->exec("ALTER TABLE gallery ADD COLUMN uploaded_by_role TEXT");
        }
        if (!columnExists($engine, 'gallery', 'uploaded_by_type')) {
            $engine->exec("ALTER TABLE gallery ADD COLUMN uploaded_by_type TEXT DEFAULT 'admin'");
        }
        if (!columnExists($engine, 'gallery', 'likes')) {
            $engine->exec("ALTER TABLE gallery ADD COLUMN likes INTEGER DEFAULT 0");
        }
    }

    // Portfolio projects table
    if (!tableExists($engine, 'portfolio_projects')) {
        $engine->exec("CREATE TABLE portfolio_projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            technologies TEXT,
            image_path TEXT,
            project_url TEXT,
            github_url TEXT,
            category TEXT,
            completion_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Courses table
    if (!tableExists($engine, 'courses')) {
        $engine->exec("CREATE TABLE courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_name TEXT NOT NULL,
            course_code TEXT,
            description TEXT,
            instructor TEXT,
            progress INTEGER DEFAULT 0,
            status TEXT DEFAULT 'In Progress',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert L4 Software Development courses
        $courses = [
            ['Software Development Fundamentals', 'L4SOD101', 'Introduction to software development principles and practices', 'Instructor', 75, 'In Progress'],
            ['Web Development', 'L4SOD102', 'HTML, CSS, JavaScript, and modern web frameworks', 'Instructor', 80, 'In Progress'],
            ['Database Design & SQL', 'L4SOD103', 'Database modeling, SQL queries, and database management', 'Instructor', 70, 'In Progress'],
            ['Programming with PHP', 'L4SOD104', 'Server-side programming with PHP and MySQL', 'Instructor', 85, 'In Progress'],
            ['Software Testing', 'L4SOD105', 'Testing methodologies, unit testing, and QA processes', 'Instructor', 60, 'In Progress'],
            ['Project Management', 'L4SOD106', 'Agile methodologies, Scrum, and project planning', 'Instructor', 65, 'In Progress'],
            ['Mobile App Development', 'L4SOD107', 'Introduction to mobile application development', 'Instructor', 50, 'In Progress'],
            ['System Analysis & Design', 'L4SOD108', 'Requirements gathering and system architecture', 'Instructor', 55, 'In Progress']
        ];
        
        $stmt = $engine->prepare("INSERT INTO courses (course_name, course_code, description, instructor, progress, status) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($courses as $course) {
            $stmt->execute($course);
        }
    }

    // Feedback/Messages table
    if (!tableExists($engine, 'feedbacks')) {
        $engine->exec("CREATE TABLE feedbacks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            phone TEXT,
            message TEXT,
            is_answered INTEGER DEFAULT 0,
            answer TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// AUTHENTICATION SYSTEM
// ==========================================
$login_error = "";
$family_login_error = "";
$message_status = "";
$current_user = null;
$is_developer = false;
$is_family_member = false;
$family_member_info = null;

// Handle regular login (Developer/Admin)
if (isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $engine->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_type'] = 'regular';
        $_SESSION['user_data'] = $user;
        header("Location: ?#dashboard");
        exit;
    } else {
        $login_error = "❌ Invalid credentials! Use: bonette / bonette2024";
    }
}

// Handle family member login
if (isset($_POST['family_login'])) {
    $name = trim($_POST['family_name']);
    $password = trim($_POST['family_password']);
    
    $stmt = $engine->prepare("SELECT * FROM family_members WHERE name = ?");
    $stmt->execute([$name]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($member && password_verify($password, $member['password'])) {
        $_SESSION['user_id'] = $member['id'];
        $_SESSION['username'] = $member['name'];
        $_SESSION['role'] = 'family';
        $_SESSION['family_role'] = $member['role'];
        $_SESSION['login_type'] = 'family';
        $_SESSION['family_data'] = $member;
        header("Location: ?#family-chat");
        exit;
    } else {
        $family_login_error = "❌ Invalid family credentials!";
    }
}

// Handle profile photo upload
if (isset($_POST['upload_profile_photo']) && isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'regular') {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'profile_' . $_SESSION['username'] . '_' . time() . '.' . $ext;
            $destination = $profile_folder . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                $stmt = $engine->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $stmt->execute([$destination, $_SESSION['user_id']]);
                $message_status = "<div class='alert alert-success'>✅ Profile photo updated successfully!</div>";
                
                // Refresh user data
                $_SESSION['user_data']['profile_photo'] = $destination;
            } else {
                $message_status = "<div class='alert alert-error'>❌ Failed to upload photo. Check folder permissions.</div>";
            }
        } else {
            $message_status = "<div class='alert alert-error'>❌ Invalid file type. Use JPG, PNG, GIF, or WebP.</div>";
        }
    } else {
        $message_status = "<div class='alert alert-error'>❌ Please select a photo to upload.</div>";
    }
}

// Handle family chat message
if (isset($_POST['send_family_chat']) && isset($_SESSION['login_type'])) {
    $message = trim($_POST['chat_message']);
    $sender_name = $_SESSION['username'];
    $sender_role = $_SESSION['login_type'] === 'family' ? ($_SESSION['family_role'] ?? 'Member') : 'Developer';
    
    if (!empty($message)) {
        $stmt = $engine->prepare("INSERT INTO family_chat (sender_id, sender_name, sender_role, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $sender_name, $sender_role, $message]);
    }
    header("Location: ?#family-chat");
    exit;
}

// Handle photo upload (Family members)
if (isset($_POST['family_upload']) && isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'family') {
    $caption = trim($_POST['caption']);
    $category = trim($_POST['category']);
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $target_dir = $category === 'family' ? $family_folder : ($category === 'activity' ? $activity_folder : $portfolio_folder);
        $filename = time() . '_' . $_SESSION['username'] . '_' . basename($_FILES['photo']['name']);
        $destination = $target_dir . $filename;
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $stmt = $engine->prepare("INSERT INTO gallery (image_path, caption, category, uploaded_by, uploaded_by_role, uploaded_by_type) VALUES (?, ?, ?, ?, ?, 'family')");
                $stmt->execute([$destination, $caption, $category, $_SESSION['username'], $_SESSION['family_role'] ?? 'Member']);
                header("Location: ?#family-photos");
                exit;
            }
        }
    }
    header("Location: ?#family");
    exit;
}

// Handle developer portfolio upload
if (isset($_POST['portfolio_upload']) && isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'regular') {
    $caption = trim($_POST['caption']);
    $category = 'portfolio';
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $filename = time() . '_portfolio_' . basename($_FILES['photo']['name']);
        $destination = $portfolio_folder . $filename;
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $stmt = $engine->prepare("INSERT INTO gallery (image_path, caption, category, uploaded_by, uploaded_by_role, uploaded_by_type) VALUES (?, ?, ?, ?, 'Developer', 'admin')");
                $stmt->execute([$destination, $caption, $category, $_SESSION['username']]);
                $message_status = "<div class='alert alert-success'>✅ Portfolio photo added successfully!</div>";
            }
        }
    }
}

// Handle contact form
if (isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);
    
    if ($name && $email && $message) {
        $stmt = $engine->prepare("INSERT INTO feedbacks (name, email, phone, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $message]);
        $message_status = "<div class='alert alert-success'>✅ Message sent successfully! I'll respond soon.</div>";
    } else {
        $message_status = "<div class='alert alert-error'>❌ Please fill all required fields.</div>";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?");
    exit;
}

// Check authentication status
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['login_type'] === 'family') {
        $stmt = $engine->prepare("SELECT * FROM family_members WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $family_member_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_family_member = true;
    } else {
        $stmt = $engine->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_developer = ($current_user && $current_user['role'] === 'developer');
    }
}

$is_authenticated = isset($_SESSION['user_id']);

// Fetch data for display
$family_members = $engine->query("SELECT * FROM family_members ORDER BY 
    CASE WHEN birth_order IS NULL THEN 0 ELSE 1 END, 
    birth_order ASC")->fetchAll(PDO::FETCH_ASSOC);

$family_photos = $engine->query("SELECT * FROM gallery WHERE category='family' ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$portfolio_photos = $engine->query("SELECT * FROM gallery WHERE category='portfolio' ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$activity_photos = $engine->query("SELECT * FROM gallery WHERE category='activity' ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$family_chat = $engine->query("SELECT * FROM family_chat ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$courses = $engine->query("SELECT * FROM courses ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$projects = $engine->query("SELECT * FROM portfolio_projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get Bonette's user data
$bonette_data = $engine->query("SELECT * FROM users WHERE username='bonette'")->fetch(PDO::FETCH_ASSOC);
$profile_photo = $bonette_data['profile_photo'] ?? null;

// Theme colors
$theme = $current_theme === 'light' ? [
    'bg' => '#f8fafc',
    'surface' => '#ffffff',
    'text' => '#1e293b',
    'text_secondary' => '#64748b',
    'primary' => '#6366f1',
    'accent' => '#ec4899',
    'border' => '#e2e8f0',
    'gradient1' => 'linear-gradient(135deg, #6366f1, #8b5cf6)',
    'gradient2' => 'linear-gradient(135deg, #ec4899, #f43f5e)',
    'success' => '#10b981',
    'error' => '#ef4444',
    'warning' => '#f59e0b',
    'header_bg' => 'rgba(255,255,255,0.95)',
] : [
    'bg' => '#0f172a',
    'surface' => '#1e293b',
    'text' => '#f1f5f9',
    'text_secondary' => '#94a3b8',
    'primary' => '#818cf8',
    'accent' => '#f472b6',
    'border' => '#334155',
    'gradient1' => 'linear-gradient(135deg, #818cf8, #a78bfa)',
    'gradient2' => 'linear-gradient(135deg, #f472b6, #fb7185)',
    'success' => '#34d399',
    'error' => '#f87171',
    'warning' => '#fbbf24',
    'header_bg' => 'rgba(15,23,42,0.95)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBYOYAKOZE Bonette | L4 Software Development | Portfolio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: <?php echo $theme['bg']; ?>;
            --surface: <?php echo $theme['surface']; ?>;
            --text: <?php echo $theme['text']; ?>;
            --text-secondary: <?php echo $theme['text_secondary']; ?>;
            --primary: <?php echo $theme['primary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --border: <?php echo $theme['border']; ?>;
            --gradient1: <?php echo $theme['gradient1']; ?>;
            --gradient2: <?php echo $theme['gradient2']; ?>;
            --success: <?php echo $theme['success']; ?>;
            --error: <?php echo $theme['error']; ?>;
            --header-bg: <?php echo $theme['header_bg']; ?>;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            transition: all 0.3s ease;
        }

        /* Background Particles */
        .bg-particles {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        .particle {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.1;
            animation: float 20s infinite;
        }
        .particle:nth-child(1) { background: var(--primary); width: 300px; height: 300px; top: 5%; left: 10%; }
        .particle:nth-child(2) { background: var(--accent); width: 250px; height: 250px; top: 50%; right: 5%; }
        .particle:nth-child(3) { background: var(--success); width: 200px; height: 200px; bottom: 10%; left: 30%; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(30px, -30px); }
            66% { transform: translate(-20px, 20px); }
        }

        /* Layout */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        section { padding: 60px 0; }

        /* Header */
        .header {
            background: var(--header-bg);
            backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }
        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--gradient1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            list-style: none;
        }
        .nav-links a {
            color: var(--text);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .nav-links a:hover { background: var(--surface); color: var(--primary); }

        /* Theme Toggle */
        .theme-toggle {
            display: flex;
            background: var(--surface);
            border-radius: 30px;
            padding: 4px;
            border: 1px solid var(--border);
        }
        .theme-toggle a {
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            color: var(--text);
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .theme-toggle a.active {
            background: var(--gradient1);
            color: white;
        }

        /* Cards */
        .card {
            background: var(--surface);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        /* Grids */
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }

        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .gallery-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        .btn-primary { background: var(--gradient1); color: white; }
        .btn-accent { background: var(--gradient2); color: white; }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-success { background: var(--success); color: white; }
        .btn-sm { padding: 8px 18px; font-size: 0.85rem; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99,102,241,0.3); }

        /* Hero Section */
        .hero {
            padding: 80px 0;
            text-align: center;
        }
        .profile-photo-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 30px;
        }
        .profile-photo {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--surface);
            box-shadow: 0 10px 40px rgba(99,102,241,0.3);
        }
        .profile-photo-placeholder {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: var(--gradient1);
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            border: 4px solid var(--surface);
            box-shadow: 0 10px 40px rgba(99,102,241,0.3);
        }
        .profile-photo-edit {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--gradient2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid var(--surface);
            transition: all 0.3s;
        }
        .profile-photo-edit:hover {
            transform: scale(1.1);
        }
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            background: var(--gradient1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero .subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        .badge {
            display: inline-block;
            background: var(--gradient2);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            margin: 5px;
            font-size: 0.9rem;
        }

        /* Info Cards */
        .info-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 25px;
            border-left: 4px solid var(--primary);
            margin-bottom: 20px;
        }
        .info-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Chat System */
        .chat-container {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            height: 500px;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            background: var(--gradient1);
            color: white;
            border-radius: 16px 16px 0 0;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .chat-message {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            line-height: 1.4;
        }
        .chat-message.sent {
            align-self: flex-end;
            background: var(--gradient1);
            color: white;
        }
        .chat-message.received {
            align-self: flex-start;
            background: var(--bg);
            border: 1px solid var(--border);
        }
        .chat-sender {
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .chat-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 5px;
        }
        .chat-input {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }
        .chat-input input {
            flex: 1;
            padding: 12px;
            border-radius: 25px;
            border: 2px solid var(--border);
            background: var(--bg);
            color: var(--text);
            outline: none;
        }
        .chat-input button {
            padding: 12px 25px;
            border-radius: 25px;
            background: var(--gradient1);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        /* Photo Cards */
        .photo-card {
            background: var(--surface);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s;
            position: relative;
        }
        .photo-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .photo-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.5s;
        }
        .photo-card:hover img { transform: scale(1.05); }
        .photo-info { padding: 15px; }
        .photo-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--gradient2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .family-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--gradient1);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 10px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: var(--gradient1);
            border-radius: 10px;
            transition: width 0.3s;
        }

        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg);
            border: 2px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }

        /* Section Titles */
        .section-title {
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 15px;
            background: var(--gradient1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .section-subtitle {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 40px;
            font-size: 1.1rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .modal.active { display: flex; }
        .modal img { max-width: 90%; max-height: 90vh; border-radius: 10px; }
        .modal-close {
            position: absolute;
            top: 20px; right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin: 15px 0;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
        }

        /* Footer */
        .footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 40px 0;
            text-align: center;
            margin-top: 60px;
        }

        .text-center { text-align: center; }
        .mb-20 { margin-bottom: 20px; }
        .mb-40 { margin-bottom: 40px; }
        .mt-40 { margin-top: 40px; }
    </style>
</head>
<body>
    <div class="bg-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Modal for Image View -->
    <div class="modal" id="imageModal" onclick="this.classList.remove('active')">
        <span class="modal-close">&times;</span>
        <img id="modalImage" src="" alt="Preview">
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-inner">
            <a href="?" class="logo">Bonette.dev</a>
            <nav>
                <ul class="nav-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#education">Courses</a></li>
                    <li><a href="#family">Family</a></li>
                    <li><a href="#family-chat">Family Chat</a></li>
                    <li><a href="#portfolio">Portfolio</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <?php if ($is_authenticated): ?>
                        <li><a href="?logout" class="btn btn-sm btn-outline">Logout</a></li>
                    <?php else: ?>
                        <li><a href="#login-section" class="btn btn-sm btn-primary">Login</a></li>
                    <?php endif; ?>
                    <li>
                        <div class="theme-toggle">
                            <a href="?set_theme=dark" class="<?php echo $current_theme === 'dark' ? 'active' : ''; ?>">🌙</a>
                            <a href="?set_theme=light" class="<?php echo $current_theme === 'light' ? 'active' : ''; ?>">☀️</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <?php if ($profile_photo && file_exists($profile_photo)): ?>
                <div class="profile-photo-container">
                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Bonette" class="profile-photo">
                    <?php if ($is_developer): ?>
                        <a href="#upload-profile-photo" class="profile-photo-edit" title="Change photo">
                            <i class="fas fa-camera"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="profile-photo-placeholder">
                    👨‍💻
                    <?php if ($is_developer): ?>
                        <a href="#upload-profile-photo" class="profile-photo-edit" title="Add your photo">
                            <i class="fas fa-camera"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h1>IBYOYAKOZE Bonette</h1>
            <p class="subtitle">
                <strong>L4 Software Development</strong> Student<br>
                LYCEE SAINT ALEXAUNDRE SAULI DE MUHURA<br>
                📍 Gatsibo | 📞 0792259699
            </p>
            <div>
                <span class="badge">🎂 Born: 15 October 2008</span>
                <span class="badge">👨‍👩‍👧‍👦 2nd Child of 8</span>
                <span class="badge">💻 Software Developer</span>
                <span class="badge">📚 L4 SOD</span>
            </div>
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="#portfolio" class="btn btn-primary">View Portfolio</a>
                <a href="#family-chat" class="btn btn-accent">Family Chat</a>
                <a href="https://wa.me/250792259699" target="_blank" class="btn" style="background: #25D366; color: white;">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <a href="https://instagram.com/bonette127" target="_blank" class="btn" style="background: #E1306C; color: white;">
                    <i class="fab fa-instagram"></i> @bonette127
                </a>
            </div>
        </div>
    </section>

    <!-- Profile Photo Upload Section (Developer Only) -->
    <?php if ($is_developer): ?>
    <section id="upload-profile-photo">
        <div class="container">
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <h3 style="color: var(--primary);">
                    <i class="fas fa-camera"></i> Upload Your Profile Photo
                </h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    Choose a clear photo of yourself. It will appear on your portfolio homepage.
                </p>
                <?php echo $message_status; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select Photo (JPG, PNG, GIF, WebP)</label>
                        <input type="file" name="profile_photo" accept="image/*" class="form-control" required>
                    </div>
                    <button type="submit" name="upload_profile_photo" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-upload"></i> Upload Profile Photo
                    </button>
                </form>
                <p style="text-align: center; margin-top: 15px; font-size: 0.85rem; color: var(--text-secondary);">
                    <strong>How to insert your photo:</strong><br>
                    1. Click "Choose File" and select your photo<br>
                    2. Click "Upload Profile Photo" button<br>
                    3. Your photo will appear at the top of your portfolio
                </p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- About Section -->
    <section id="about">
        <div class="container">
            <h2 class="section-title">About Me</h2>
            <p class="section-subtitle">Software developer in the making</p>
            
            <div class="grid-2">
                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <p><strong>Full Name:</strong> IBYOYAKOZE Bonette</p>
                    <p><strong>Date of Birth:</strong> 15 October 2008 (Age: <?php echo date('Y') - 2008; ?>)</p>
                    <p><strong>Position in Family:</strong> 2nd Child (of 8)</p>
                    <p><strong>Phone:</strong> 0792259699</p>
                    <p><strong>WhatsApp:</strong> +250 792 259 699</p>
                    <p><strong>Instagram:</strong> @bonette127</p>
                    <p><strong>Facebook:</strong> Ibyoyakoze Bonette</p>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-graduation-cap"></i> Education</h3>
                    <p><strong>Current School:</strong> LYCEE SAINT ALEXAUNDRE SAULI DE MUHURA</p>
                    <p><strong>Course:</strong> L4 Software Development</p>
                    <p><strong>Primary School:</strong> G.S KIBONDO</p>
                    <p><strong>Specialization:</strong> Software Engineering</p>
                    <p><strong>Status:</strong> Active Student</p>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-code"></i> Technical Skills</h3>
                    <p><strong>Languages:</strong> PHP, SQL, HTML, CSS, JavaScript, Python</p>
                    <p><strong>Areas:</strong> Web Development, Database Design</p>
                    <p><strong>Tools:</strong> VS Code, Git, MySQL, XAMPP</p>
                    <p><strong>Methodologies:</strong> Agile, Scrum</p>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-heart"></i> Family Background</h3>
                    <p><strong>Father:</strong> Jack</p>
                    <p><strong>Mother:</strong> Oliva</p>
                    <p><strong>Brothers:</strong> Cedric (1st born), Mugisha (3rd born)</p>
                    <p><strong>Sisters:</strong> Ruth (4th), Reponce (5th), Beryse (6th)</p>
                    <p><strong>Total Family Members:</strong> 8</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Education & Courses Section -->
    <section id="education">
        <div class="container">
            <h2 class="section-title">My Courses - L4 Software Development</h2>
            <p class="section-subtitle">Currently studying at LYCEE SAINT ALEXAUNDRE SAULI DE MUHURA</p>
            
            <div class="grid-3">
                <?php foreach ($courses as $course): ?>
                    <div class="card">
                        <h3 style="color: var(--primary);"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                        <p style="color: var(--text-secondary); margin: 10px 0;">
                            <small>Code: <?php echo htmlspecialchars($course['course_code']); ?></small>
                        </p>
                        <p style="margin-bottom: 15px;"><?php echo htmlspecialchars($course['description']); ?></p>
                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="font-size: 0.9rem;">Progress</span>
                                <span style="font-size: 0.9rem; font-weight: 600;"><?php echo $course['progress']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%;"></div>
                            </div>
                        </div>
                        <span class="badge" style="margin-top: 15px; display: inline-block; font-size: 0.8rem;">
                            <?php echo $course['status']; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Family Section -->
    <section id="family">
        <div class="container">
            <h2 class="section-title">My Family - 8 Members</h2>
            <p class="section-subtitle">The second child in a wonderful family of 8</p>
            
            <div class="grid-4 mb-40">
                <?php
                $icons = ['Father' => '👨', 'Mother' => '👩', 'Brother' => '👦', 'Sister' => '👧', 'Self' => '🧑‍💻'];
                foreach ($family_members as $member):
                    $is_bonette = ($member['name'] === 'Bonette');
                ?>
                    <div class="card text-center" style="<?php echo $is_bonette ? 'border: 3px solid var(--primary);' : ''; ?>">
                        <div style="font-size: 3rem;"><?php echo $icons[$member['role']] ?? '👤'; ?></div>
                        <h3><?php echo htmlspecialchars($member['name']); ?></h3>
                        <p style="color: var(--accent); font-weight: 600;"><?php echo htmlspecialchars($member['role']); ?></p>
                        <?php if ($member['birth_order']): ?>
                            <small style="color: var(--text-secondary);">
                                Child #<?php echo $member['birth_order']; ?>
                                <?php if ($is_bonette): ?>
                                    <br>🎂 15-10-2008
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Family Login & Upload -->
            <?php if ($is_family_member): ?>
                <div class="card mb-40" style="max-width: 600px; margin: 0 auto;">
                    <h3 style="color: var(--primary);">
                        Welcome, <?php echo htmlspecialchars($family_member_info['name']); ?>!
                        <small style="color: var(--accent);">(<?php echo htmlspecialchars($family_member_info['role']); ?>)</small>
                    </h3>
                    <p style="color: var(--text-secondary); margin-bottom: 15px;">
                        Share your family moments by uploading photos below.
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Choose Photo</label>
                            <input type="file" name="photo" accept="image/*" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="family">Family Photo</option>
                                <option value="activity">Daily Activity</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Caption</label>
                            <input type="text" name="caption" class="form-control" placeholder="Describe this photo..." required>
                        </div>
                        <button type="submit" name="family_upload" class="btn btn-accent" style="width: 100%;">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Photo
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="card mb-40" style="max-width: 500px; margin: 0 auto;">
                    <h3 style="color: var(--primary);">👨‍👩‍👧‍👦 Family Member Login</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 15px;">
                        Login to upload photos and join the family chat.
                    </p>
                    <?php if ($family_login_error): ?>
                        <div class="alert alert-error"><?php echo $family_login_error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Your Name</label>
                            <select name="family_name" class="form-control" required>
                                <option value="">Select your name...</option>
                                <?php foreach ($family_members as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['name']); ?>">
                                        <?php echo htmlspecialchars($m['name']); ?> (<?php echo $m['role']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="family_password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" name="family_login" class="btn btn-primary" style="width: 100%;">
                            Login to Upload & Chat
                        </button>
                    </form>
                    <p style="text-align: center; margin-top: 15px; font-size: 0.8rem; color: var(--text-secondary);">
                        Each family member has their own password to share photos!
                    </p>
                </div>
            <?php endif; ?>

            <!-- Family Photos -->
            <h3 style="color: var(--accent); margin-bottom: 20px;" id="family-photos">
                <i class="fas fa-images"></i> Family Photo Gallery
            </h3>
            <?php if (count($family_photos) > 0): ?>
                <div class="gallery-grid">
                    <?php foreach ($family_photos as $photo): ?>
                        <div class="photo-card">
                            <?php if (file_exists($photo['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($photo['caption']); ?>" 
                                     onclick="openModal('<?php echo htmlspecialchars($photo['image_path']); ?>')">
                            <?php else: ?>
                                <div style="height: 250px; background: var(--surface); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image" style="font-size: 3rem; color: var(--text-secondary);"></i>
                                </div>
                            <?php endif; ?>
                            <div class="family-badge">👤 <?php echo htmlspecialchars($photo['uploaded_by']); ?></div>
                            <div class="photo-badge"><?php echo htmlspecialchars($photo['category']); ?></div>
                            <div class="photo-info">
                                <p><strong><?php echo htmlspecialchars($photo['caption']); ?></strong></p>
                                <small style="color: var(--text-secondary);">
                                    By <?php echo htmlspecialchars($photo['uploaded_by']); ?> 
                                    (<?php echo htmlspecialchars($photo['uploaded_by_role'] ?? 'Member'); ?>)
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card text-center" style="padding: 50px;">
                    <i class="fas fa-camera" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 15px;"></i>
                    <p style="color: var(--text-secondary);">
                        No family photos yet. Family members, login to upload your photos!
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Family Chat Section -->
    <section id="family-chat">
        <div class="container">
            <h2 class="section-title">💬 Family Chat</h2>
            <p class="section-subtitle">Our private family conversation space</p>
            
            <?php if ($is_authenticated): ?>
                <div class="chat-container">
                    <div class="chat-header">
                        <i class="fas fa-comments"></i> 
                        Family Chat - Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                        <?php if (isset($_SESSION['family_role'])): ?>
                            <small style="opacity: 0.9;">(<?php echo htmlspecialchars($_SESSION['family_role']); ?>)</small>
                        <?php endif; ?>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($family_chat) > 0): ?>
                            <?php foreach (array_reverse($family_chat) as $msg): ?>
                                <div class="chat-message <?php echo ($msg['sender_name'] === $_SESSION['username']) ? 'sent' : 'received'; ?>">
                                    <div class="chat-sender" style="color: <?php echo ($msg['sender_name'] === $_SESSION['username']) ? 'white' : 'var(--primary)'; ?>;">
                                        <?php echo htmlspecialchars($msg['sender_name']); ?> 
                                        (<?php echo htmlspecialchars($msg['sender_role']); ?>)
                                    </div>
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    <div class="chat-time"><?php echo date('d M H:i', strtotime($msg['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 50px; color: var(--text-secondary);">
                                <i class="fas fa-comment-dots" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="chat-input">
                        <input type="text" name="chat_message" placeholder="Type your message to the family..." required autocomplete="off">
                        <button type="submit" name="send_family_chat"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="card text-center" style="max-width: 500px; margin: 0 auto; padding: 50px;">
                    <i class="fas fa-lock" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 15px;"></i>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">
                        Login to join the family conversation!
                    </p>
                    <a href="#family" class="btn btn-primary">Go to Family Login</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Portfolio Section -->
    <section id="portfolio">
        <div class="container">
            <h2 class="section-title">My Portfolio</h2>
            <p class="section-subtitle">Projects, achievements, and work showcase</p>
            
            <?php if ($is_developer): ?>
                <div class="card mb-40" style="max-width: 600px; margin: 0 auto;">
                    <h3 style="color: var(--primary);">
                        <i class="fas fa-upload"></i> Add Portfolio Photo
                    </h3>
                    <p style="color: var(--text-secondary); margin-bottom: 15px;">
                        Upload screenshots of your projects, certificates, or achievements.
                    </p>
                    <?php echo $message_status; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Choose Photo</label>
                            <input type="file" name="photo" accept="image/*" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Caption / Description</label>
                            <input type="text" name="caption" class="form-control" placeholder="Project name or description..." required>
                        </div>
                        <button type="submit" name="portfolio_upload" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-plus-circle"></i> Add to Portfolio
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="gallery-grid">
                <?php if (count($portfolio_photos) > 0): ?>
                    <?php foreach ($portfolio_photos as $photo): ?>
                        <div class="photo-card">
                            <?php if (file_exists($photo['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($photo['caption']); ?>"
                                     onclick="openModal('<?php echo htmlspecialchars($photo['image_path']); ?>')">
                            <?php else: ?>
                                <div style="height: 250px; background: var(--surface); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--text-secondary);"></i>
                                </div>
                            <?php endif; ?>
                            <div class="photo-info">
                                <p><strong><?php echo htmlspecialchars($photo['caption']); ?></strong></p>
                                <small style="color: var(--text-secondary);">
                                    Added <?php echo date('d M Y', strtotime($photo['uploaded_at'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card text-center" style="grid-column: 1/-1; padding: 50px;">
                        <i class="fas fa-briefcase" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">
                            Portfolio items coming soon! Login as developer to add your work.
                        </p>
                        <?php if (!$is_authenticated): ?>
                            <a href="#login-section" class="btn btn-primary mt-20">Login to Add Portfolio</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact">
        <div class="container">
            <h2 class="section-title">Contact Me</h2>
            <p class="section-subtitle">
                📞 0792259699 | 
                <a href="https://wa.me/250792259699" target="_blank" style="color: #25D366; text-decoration: none;">WhatsApp</a> |
                <a href="https://instagram.com/bonette127" target="_blank" style="color: #E1306C; text-decoration: none;">@bonette127</a> |
                <a href="https://facebook.com/Ibyoyakoze Bonette" target="_blank" style="color: #1877F2; text-decoration: none;">Facebook</a>
            </p>
            
            <?php echo $message_status; ?>
            
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <form method="POST">
                    <div class="form-group">
                        <label>Your Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="Full name" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>Phone (optional)</label>
                        <input type="tel" name="phone" class="form-control" placeholder="0792...">
                    </div>
                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="4" class="form-control" placeholder="Your message..." required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <?php if (!$is_authenticated): ?>
    <section id="login-section">
        <div class="container">
            <div class="card" style="max-width: 450px; margin: 0 auto;">
                <h2 class="text-center mb-20" style="color: var(--primary);">
                    <i class="fas fa-lock"></i> Developer Login
                </h2>
                <p style="text-align: center; color: var(--text-secondary); margin-bottom: 20px;">
                    Login to manage your portfolio, upload profile photo, and coordinate family activities.
                </p>
                <?php if ($login_error): ?>
                    <div class="alert alert-error"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" placeholder="bonette" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="bonette2024" required>
                    </div>
                    <button type="submit" name="login_submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Login as Developer
                    </button>
                </form>
                <div style="margin-top: 20px; padding: 15px; background: var(--bg); border-radius: 10px; text-align: center;">
                    <p style="color: var(--text-secondary); font-size: 0.85rem;">
                        <strong>Demo Credentials:</strong><br>
                        Username: <code>bonette</code><br>
                        Password: <code>bonette2024</code>
                    </p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p><strong>IBYOYAKOZE Bonette</strong> | L4 Software Development</p>
            <p>LYCEE SAINT ALEXAUNDRE SAULI DE MUHURA | G.S KIBONDO Alumni</p>
            <p>Born: 15 October 2008 | 2nd Child of 8 | 📞 0792259699</p>
            <div style="margin-top: 15px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="https://instagram.com/bonette127" target="_blank" style="color: #E1306C; text-decoration: none;">
                    <i class="fab fa-instagram"></i> @bonette127
                </a>
                <a href="https://facebook.com/Ibyoyakoze Bonette" target="_blank" style="color: #1877F2; text-decoration: none;">
                    <i class="fab fa-facebook"></i> Facebook
                </a>
                <a href="https://wa.me/250792259699" target="_blank" style="color: #25D366; text-decoration: none;">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <a href="?set_theme=dark" style="color: var(--text); text-decoration: none;">🌙 Dark</a>
                <a href="?set_theme=light" style="color: var(--text); text-decoration: none;">☀️ Light</a>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Float -->
    <a href="https://wa.me/250792259699" target="_blank" style="position: fixed; bottom: 30px; right: 30px; background: #25D366; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; z-index: 999; box-shadow: 0 5px 25px rgba(37,211,102,0.3); text-decoration: none; animation: pulse-wa 2s infinite;">
        <i class="fab fa-whatsapp"></i>
    </a>

    <style>
        @keyframes pulse-wa {
            0% { box-shadow: 0 0 0 0 rgba(37,211,102,0.4); }
            70% { box-shadow: 0 0 0 20px rgba(37,211,102,0); }
            100% { box-shadow: 0 0 0 0 rgba(37,211,102,0); }
        }
    </style>

    <script>
        function openModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('active');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('imageModal').classList.remove('active');
            }
        });

        // Auto-scroll chat to bottom
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>