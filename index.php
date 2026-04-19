<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ResQLink - Disaster Shelter & Evacuation Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <span class="badge bg-danger">ResQLink</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white ms-2" href="pages/register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container d-flex align-items-center justify-content-center h-100">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bold mb-4">ResQLink</h1>
                <p class="lead mb-4">Real-Time Disaster Management & Evacuation Coordination System</p>
                <p class="mb-5">Ensuring Safe Evacuation and Efficient Shelter Management During Disasters</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="pages/login.php" class="btn btn-danger btn-lg">Login</a>
                    <a href="pages/register.php" class="btn btn-outline-light btn-lg">Register Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section py-5">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Key Features</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h5 class="mt-3">Real-Time Alerts</h5>
                        <p>Receive instant disaster alerts with critical information and safety instructions.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h5 class="mt-3">Shelter Management</h5>
                        <p>Find nearby shelters with real-time capacity information and location details.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5 class="mt-3">Track Status</h5>
                        <p>Update your evacuation status and let authorities know you're safe.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="mt-3">Rescue Coordination</h5>
                        <p>Efficient coordination between rescue teams and emergency services.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h5 class="mt-3">Dashboard</h5>
                        <p>Comprehensive monitoring dashboard for administrators and officials.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5 class="mt-3">Mobile Responsive</h5>
                        <p>Access the system anytime, anywhere on any device during emergencies.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h5 class="mt-3">Secure</h5>
                        <p>Role-based access control with encrypted passwords and secure authentication.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h5 class="mt-3">Reports</h5>
                        <p>Generate detailed reports on disaster response and evacuation statistics.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">About ResQLink</h2>
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4">
                    <h4 class="mb-3">Addressing Disaster Management Challenges</h4>
                    <p>ResQLink is a comprehensive web-based platform designed to address the critical gaps in disaster response coordination. During natural disasters like floods, cyclones, and earthquakes, timely communication and efficient resource management are crucial.</p>
                    <p class="mt-3"><strong>Our system provides:</strong></p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-danger"></i> Centralized real-time information hub</li>
                        <li><i class="fas fa-check text-danger"></i> Structured evacuation planning</li>
                        <li><i class="fas fa-check text-danger"></i> Efficient shelter capacity management</li>
                        <li><i class="fas fa-check text-danger"></i> Seamless rescue team coordination</li>
                        <li><i class="fas fa-check text-danger"></i> Accurate disaster monitoring</li>
                        <li><i class="fas fa-check text-danger"></i> Post-disaster recovery guidance</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="about-image bg-danger rounded p-5 text-white text-center">
                        <i class="fas fa-heartbeat" style="font-size: 80px; opacity: 0.3;"></i>
                        <p class="mt-3"><strong>Saving Lives Through Technology</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- User Types Section -->
    <section class="user-types-section py-5">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Who Can Use ResQLink?</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="user-card">
                        <h5><i class="fas fa-user-circle text-danger"></i> Citizens</h5>
                        <p>Receive alerts, find shelters, update evacuation status</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="user-card">
                        <h5><i class="fas fa-user-tie text-danger"></i> Administrators</h5>
                        <p>Manage alerts, shelters, resources, and monitor systems</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="user-card">
                        <h5><i class="fas fa-people-carry text-danger"></i> Rescue Teams</h5>
                        <p>Coordinate operations and manage rescue missions</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="user-card">
                        <h5><i class="fas fa-building text-danger"></i> Government Bodies</h5>
                        <p>Monitor statistics and analyze disaster response</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="user-card">
                        <h5><i class="fas fa-cog text-danger"></i> System Admins</h5>
                        <p>Maintain systems, manage accounts, ensure security</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="user-card">
                        <h5><i class="fas fa-info-circle text-danger"></i> All Users</h5>
                        <p>Fast, secure, and reliable disaster management</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section bg-danger text-white py-5">
        <div class="container text-center">
            <h2 class="mb-4 fw-bold">Be Prepared, Stay Safe</h2>
            <p class="lead mb-4">Join ResQLink today and be part of a safer community during emergencies</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="pages/register.php" class="btn btn-light btn-lg">Create Account</a>
                <a href="pages/login.php" class="btn btn-outline-light btn-lg">Already Registered?</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h6 class="text-danger">ResQLink</h6>
                    <p>Disaster Management System</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h6 class="text-danger">Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#home" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="#features" class="text-white-50 text-decoration-none">Features</a></li>
                        <li><a href="#about" class="text-white-50 text-decoration-none">About</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h6 class="text-danger">Contact</h6>
                    <p class="small text-white-50">Emergency Hotline: 999<br>Email: support@resqlink.com</p>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-white-50 small">
                <p>&copy; 2026 ResQLink - Disaster Shelter & Evacuation Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>