<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// File icon helper
function getFileIcon($extension) {
    $icons = [
        'pdf' => '<i class="fas fa-file-pdf"></i>',
        'doc' => '<i class="fas fa-file-word"></i>',
        'docx' => '<i class="fas fa-file-word"></i>',
        'xls' => '<i class="fas fa-file-excel"></i>',
        'xlsx' => '<i class="fas fa-file-excel"></i>',
        'ppt' => '<i class="fas fa-file-powerpoint"></i>',
        'pptx' => '<i class="fas fa-file-powerpoint"></i>',
        'jpg' => '<i class="fas fa-file-image"></i>',
        'jpeg' => '<i class="fas fa-file-image"></i>',
        'png' => '<i class="fas fa-file-image"></i>',
        'gif' => '<i class="fas fa-file-image"></i>',
        'zip' => '<i class="fas fa-file-archive"></i>',
        'rar' => '<i class="fas fa-file-archive"></i>',
        'mp4' => '<i class="fas fa-file-video"></i>',
        'mp3' => '<i class="fas fa-file-audio"></i>',
        'txt' => '<i class="fas fa-file-alt"></i>',
        'default' => '<i class="fas fa-file"></i>'
    ];
    $ext = strtolower($extension);
    return $icons[$ext] ?? $icons['default'];
}

// Get filter from GET parameter if set
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid_filters = ['all', 'image', 'document', 'video', 'audio', 'archive'];

// Validate filter
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

// Base query - removed join with non-existent groups table
$sql = "SELECT gm.*, u.name AS sender_name 
        FROM group_messages gm
        JOIN users u ON gm.sender_id = u.id
        JOIN group_members gmem ON gm.group_id = gmem.group_id
        WHERE gmem.user_id = ? AND gm.media_path IS NOT NULL";

// Add filter conditions
switch ($filter) {
    case 'image':
        $sql .= " AND (gm.media_path LIKE '%.jpg%' OR gm.media_path LIKE '%.jpeg%' OR gm.media_path LIKE '%.png%' OR gm.media_path LIKE '%.gif%')";
        break;
    case 'document':
        $sql .= " AND (gm.media_path LIKE '%.pdf%' OR gm.media_path LIKE '%.doc%' OR gm.media_path LIKE '%.docx%' OR gm.media_path LIKE '%.xls%' OR gm.media_path LIKE '%.xlsx%' OR gm.media_path LIKE '%.ppt%' OR gm.media_path LIKE '%.pptx%' OR gm.media_path LIKE '%.txt%')";
        break;
    case 'video':
        $sql .= " AND (gm.media_path LIKE '%.mp4%' OR gm.media_path LIKE '%.mov%' OR gm.media_path LIKE '%.avi%')";
        break;
    case 'audio':
        $sql .= " AND (gm.media_path LIKE '%.mp3%' OR gm.media_path LIKE '%.wav%')";
        break;
    case 'archive':
        $sql .= " AND (gm.media_path LIKE '%.zip%' OR gm.media_path LIKE '%.rar%')";
        break;
}

$sql .= " ORDER BY gm.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Media Files</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Media Grid Layout */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .media-card {
            background: #fafafa;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .media-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Media Card Elements */
        .media-icon {
            font-size: 48px;
            margin-bottom: 10px;
            color: #3498db;
        }

        .media-filename {
            font-weight: bold;
            margin-bottom: 5px;
            word-break: break-word;
            color: #333;
        }

        .media-meta {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        /* Buttons and Links */
        .download-link {
            display: inline-block;
            padding: 8px 15px;
            background: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            margin-top: 5px;
        }

        .download-link:hover {
            background: #219955;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
            padding: 8px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #2980b9;
        }

        /* Filter Controls */
        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            background: #e0e0e0;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        .filter-btn:hover {
            background: #d0d0d0;
        }

        .filter-btn.active {
            background: #3498db;
            color: white;
        }

        /* Empty State */
        .no-media {
            text-align: center;
            color: #777;
            margin-top: 50px;
            padding: 30px;
            background: #f9f9f9;
            border-radius: 10px;
        }

        .no-media i {
            font-size: 40px;
            margin-bottom: 15px;
            color: #ccc;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .filter-container {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .media-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-btn {
                flex-grow: 1;
                justify-content: center;
            }
        }

        /* Additional styles from the inline elements */
        h2 i.fas.fa-photo-video {
            margin-right: 10px;
        }

        .back-btn i.fas.fa-arrow-left {
            margin-right: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <div style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h2 style="margin: 0; color: #333; font-size: 1.5rem;">
                    <i class="fas fa-photo-video" style="margin-right: 10px;"></i> Uploaded Group Media
                </h2>
                <a href="./admin_dashboard.php" 
                    style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-family: Arial, sans-serif; transition: background-color 0.3s ease;" 
                    onmouseover="this.style.backgroundColor='#0056b3'" 
                    onmouseout="this.style.backgroundColor='#007bff'">
                    Go Back to Dashboard
                </a>
            </div>
     </div>   
    <div class="filter-container">
        <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All Files
        </a>
        <a href="?filter=image" class="filter-btn <?= $filter === 'image' ? 'active' : '' ?>">
            <i class="fas fa-image"></i> Images
        </a>
        <a href="?filter=document" class="filter-btn <?= $filter === 'document' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> Documents
        </a>
        <a href="?filter=video" class="filter-btn <?= $filter === 'video' ? 'active' : '' ?>">
            <i class="fas fa-video"></i> Videos
        </a>
        <a href="?filter=audio" class="filter-btn <?= $filter === 'audio' ? 'active' : '' ?>">
            <i class="fas fa-music"></i> Audio
        </a>
        <a href="?filter=archive" class="filter-btn <?= $filter === 'archive' ? 'active' : '' ?>">
            <i class="fas fa-file-archive"></i> Archives
        </a>
    </div>
    
    <?php if ($result->num_rows > 0): ?>
        <div class="media-grid">
            <?php while ($row = $result->fetch_assoc()): 
                $file_path = $row['media_path'];
                $filename = basename($file_path);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $icon = getFileIcon($ext);
            ?>
                <div class="media-card">
                    <div class="media-icon"><?= $icon ?></div>
                    <div class="media-filename"><?= htmlspecialchars($filename) ?></div>
                    <div class="media-meta">
                        By <?= htmlspecialchars($row['sender_name']) ?><br>
                        <?= date('M d, Y g:i a', strtotime($row['created_at'])) ?>
                    </div>
                    <a class="download-link" href="../uploads/<?= htmlspecialchars($filename) ?>" download>
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-media">
            <i class="fas fa-folder-open fa-2x"></i><br>
            No media files found <?= $filter !== 'all' ? 'in this category' : '' ?>.
        </div>
    <?php endif; ?>
</div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>