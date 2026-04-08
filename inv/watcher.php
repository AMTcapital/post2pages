<?php
// 1. Configuration
$jsonFile = 'inventory.json';
// We will pull the phone number from an Environment Variable (GitHub Secret)
$phone = getenv('MY_PHONE_NUMBER'); 
$gateway = $phone . '@tmomail.net';

// 2. Load Inventory
if (!file_exists($jsonFile)) {
    die("Error: $jsonFile not found.");
}

$data = json_decode(file_get_contents($jsonFile), true);
$total = count($data);
$postedCount = 0;

foreach ($data as $item) {
    if (isset($item['posted']) && $item['posted'] === true) {
        $postedCount++;
    }
}

// 3. Calculate Progress
$currentProgress = ($total > 0) ? ($postedCount / $total) * 100 : 0;
$message = "eBay-to-FB Update: Progress is at " . round($currentProgress, 1) . "%. ($postedCount/$total items)";

// 4. Send via PHP Mail
$sent = mail($gateway, "Inventory Progress", $message);

if ($sent) {
    echo "Notification attempt sent to $gateway at " . round($currentProgress, 1) . "%";
} else {
    echo "Mail function failed to execute.";
}
?>
