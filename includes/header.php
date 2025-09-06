<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'HAMS'; ?> - <?php echo APP_NAME; ?></title>
    <style>
        /* Critical CSS loaded inline to prevent sidebar flash */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden !important; margin: 0 !important; padding: 0 !important; }
        .sidebar {
            width: 250px !important;
            background-color: #07bbc1 !important;
            color: #FFFFFF !important;
            position: fixed !important;
            height: 100vh !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 1000 !important;
            transform: translate3d(0, 0, 0) !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .main-content {
            margin-left: 250px !important;
            background-color: #FFFFFF !important;
            min-height: 100vh !important;
        }
        .container { display: flex !important; min-height: 100vh !important; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%) !important; }
            .sidebar.active { transform: translateX(0) !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/style.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="../assets/css/style.css"></noscript>
</head>
<body>
