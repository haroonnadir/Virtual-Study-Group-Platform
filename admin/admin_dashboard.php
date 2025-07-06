<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Virtual Study Group Platform</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
:root {
  --primary-color: #2c3e50;
  --secondary-color: #3f3d99;
  --accent-color: #4f4f9f;
  --text-light: #f8f9fa;
  --text-dark: #343a40;
  --card-bg: #ffffff;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f5f7fa;
  margin: 0;
  padding: 0;
  color: var(--text-dark);
}

.sidebar {
  width: 280px;
  background-color: var(--primary-color);
  color: var(--text-light);
  position: fixed;
  height: 100vh;
  padding: 30px 25px;
  box-sizing: border-box;
  transition: all 0.3s;
  z-index: 1000;
  overflow-y: scroll;
}

.sidebar h2 {
  font-weight: 600;
  margin-bottom: 30px;
  font-size: 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  padding-bottom: 15px;
}

.sidebar .admin-info {
  display: flex;
  align-items: center;
  margin-bottom: 30px;
  padding: 12px;
  background: rgba(255,255,255,0.1);
  border-radius: 6px;
}

.sidebar .admin-info i {
  font-size: 1.3rem;
  margin-right: 12px;
}

.sidebar a {
  color: var(--text-light);
  text-decoration: none;
  padding: 14px 18px;
  display: block;
  border-radius: 6px;
  margin-bottom: 8px;
  transition: all 0.3s;
}

.sidebar a:hover,
.sidebar a.active {
  background-color: var(--secondary-color);
  padding-left: 24px;
}

.sidebar i {
  margin-right: 12px;
  width: 20px;
  text-align: center;
}

.main {
  margin-left: 280px;
  padding: 35px;
  transition: all 0.3s;
}

.header {
  background: var(--card-bg);
  padding: 24px;
  font-size: 1.5rem;
  font-weight: 600;
  text-align: center;
  border-radius: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  margin-bottom: 40px;
}

.dashboard-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 24px;
  margin-bottom: 50px;
}

.tile {
  background: var(--primary-color);
  border-radius: 10px;
  padding: 30px 20px;
  text-align: center;
  box-shadow: 0 6px 10px rgba(0,0,0,0.05);
  transition: all 0.3s;
  color: white;
  text-decoration: none;
}

.tile:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 18px rgba(0,0,0,0.1);
}

.tile i {
  font-size: 2.2rem;
  margin-bottom: 16px;
  color: var(--accent-color);
}

.logout-btn {
  background: red;
  color: white;
  padding: 14px;
  border: none;
  border-radius: 6px;
  margin-top: 25px;
  cursor: pointer;
  width: 100%;
  font-weight: 500;
  transition: all 0.3s;
}

.logout-btn:hover {
  background: #3a3a7a;
}

.chart-container {
  background: var(--card-bg);
  border-radius: 10px;
  padding: 30px;
  box-shadow: 0 6px 12px rgba(0,0,0,0.05);
  margin: 0 auto 40px auto;
  width: 90%;
  max-width: 600px;
}

.chart-title {
  text-align: center;
  margin-bottom: 25px;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-dark);
}

.chart-container canvas {
  width: 100% !important;
  height: 400px !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .sidebar {
    width: 100%;
    height: auto;
    position: relative;
    padding: 20px;
  }

  .main {
    margin-left: 0;
    padding: 20px;
  }

  .dashboard-tiles {
    grid-template-columns: 1fr 1fr;
  }

  .chart-container {
    padding: 20px;
  }
}

@media (max-width: 480px) {
  .dashboard-tiles {
    grid-template-columns: 1fr;
  }

  .chart-container {
    width: 100%;
    padding: 15px;
  }

  .chart-container canvas {
    height: auto !important;
  }
}

  </style>
</head>
<body>

<div class="sidebar">
  <h2><i class="fas fa-graduation-cap"></i> Virtual Study Group</h2>
  <div class="admin-info">
    <i class="fas fa-user-shield"></i>
    <div>
      <strong>Admin Panel</strong>
      <div style="font-size: 0.8rem;"><?php echo $_SESSION['admin_name'] ?? 'Administrator'; ?></div>
    </div>
  </div>
  
  <a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  <a href="admin_view_allgroups.php "><i class="fas fa-users"></i> View Groups</a>
  <a href="admin_manage_group_discussions.php"><i class="fas fa-comments"></i> View Discussions</a>
  <a href="admin_group_resources.php"><i class="fas fa-book"></i> View Resources</a>
  <a href="admin_manage_reports.php"><i class="fas fa-chart-bar"></i> View Reports</a>
  <a href="admin_manage_students.php"><i class="fas fa-user-graduate"></i> Students</a>
  <a href="admin_manage_profile.php"><i class="fas fa-user-cog"></i> Manage Profile</a>
  
  <form action="../logout.php" method="post">
    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
  </form>
</div>

<div class="main">
  <div class="header">
    <i class="fas fa-chart-line"></i> Admin Dashboard Overview
  </div>
  
  <div class="dashboard-tiles">
    <a href="admin_group_resources.php" class="tile">
      <i class="fas fa-book-open"></i><br>
      <strong>View Resources</strong>
    </a>
    
    <a href="admin_manage_group_discussions.php" class="tile">
      <i class="fas fa-comment-dots"></i><br>
      <strong>View Discussions</strong>
    </a>
    
    <a href="admin_view_allgroups.php" class="tile">
      <i class="fas fa-users"></i><br>
      <strong>View Groups</strong>
    </a>
    
    <a href="admin_manage_reports.php" class="tile">
      <i class="fas fa-file-alt"></i><br>
      <strong>View Reports</strong>
    </a>
    
    <a href="admin_manage_students.php" class="tile">
      <i class="fas fa-user-graduate"></i><br>
      <strong>Students</strong>
    </a>


  </div>
  
  
</div>



</body>
</html>