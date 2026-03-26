<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$campID = getenv("campID");
$toolID = getenv("toolID");
$rotationID = getenv("rotationID");
$facebookPages = [
    ['id' => getenv('FB_PAGE_FBQ_ID'), 'token' => getenv('FB_PAGE_FBQ_TOKEN')],
    ['id' => getenv('FB_PAGE_ESQ_ID'), 'token' => getenv('FB_PAGE_ESQ_TOKEN')]
];

$inventoryFile = __DIR__ . "/fbq.json";

if (!file_exists($inventoryFile)) {
    die("fbq.json not found");
}

$items = json_decode(file_get_contents($inventoryFile), true);
if (!$items) {
    die("Error reading fbq.json");
}

// FIND NEXT UNPOSTED ITEM
$nextItem = null;
$nextIndex = null;
foreach ($items as $index => $item) {
    if (empty($item["posted"])) {
        $nextItem = $item;
        $nextIndex = $index;
        break;
    }
}

if ($nextItem === null) {
    die("All items have been posted.");
}

// BUILD POST DATA
$title = $nextItem["title"];
$price = $nextItem["price"];
$currency = $nextItem["currency"] ?? $nextItem["currencyID"] ?? "USD"; 


$itemID = $nextItem['itemID'];
$affiliateUrl = "https://www.ebay.com/itm/{$itemID}?mkevt=1&mkcid=1&mkrid={$rotationID}&campid={$campID}&toolid={$toolID}&customid=facebook_bot";

// Generate Dynamic Hashtags - FIXED: changed $item to $nextItem
$dynamicTags = getHashtagsFromTitle($nextItem['title']);
$hashtags = "\n\n#eBayseller #eBayFinds #esquireattire " . $dynamicTags . " #ad";

// Final Message
$message = $title . "\nPrice: " . $price . " " . $currency . $hashtags;

// SEND POST TO FACEBOOK
$atLeastOneSuccess = false;

foreach ($facebookPages as $page) {
    $currentPageId = $page['id'];
    $currentPageToken = $page['token'];

    if (!$currentPageId || !$currentPageToken) continue;

    $endpoint = "https://graph.facebook.com/v19.0/$currentPageId/feed";

    $postData = [
        "message" => $message,
        "link" => $affiliateUrl,
        "access_token" => $currentPageToken
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result["id"])) {
        echo "Successfully posted to Page $currentPageId\n";
        $atLeastOneSuccess = true; 
    } else {
        echo "Error posting to Page $currentPageId: " . $response . "\n";
    }

    sleep(10); 
}

// SAVE STATUS
if ($atLeastOneSuccess) {
    $items[$nextIndex]["posted"] = true;
    // Use JSON_PRETTY_PRINT so the git diff is easy to read
    file_put_contents($inventoryFile, json_encode($items, JSON_PRETTY_PRINT));
    echo "\nFinal Status: Item marked as posted in inventory.";
} else {
    echo "\nFinal Status: Item was NOT marked as posted because all pages failed.";
    exit(1); 
}

// Helper Function
function getHashtagsFromTitle($title) {
    $stopWords = ['the', 'and', 'with', 'for', 'from', 'this', 'that', 'your', 'shipping', 'new', 'used'];
    $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', strtolower($cleanTitle));
    
    $tags = [];
    foreach ($words as $word) {
        if (strlen($word) > 3 && !in_array($word, $stopWords)) {
            $tags[] = '#' . ucfirst($word);
        }
    }
    return implode(' ', array_slice($tags, 0, 3));
}
?>
