<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$campID = getenv('campID');
$toolID = getenv('toolID'); 
$rotationID = getenv('rotationID');

$fbpages = [
    ['id' => getenv('FB_PAGE_ID_1'), 'token' => getenv('FB_PAGE_TOKEN_1')],
    ['id' => getenv('FB_PAGE_ID_2'), 'token' => getenv('FB_PAGE_TOKEN_2')],
    ['id' => getenv('FB_PAGE_ID_3'), 'token' => getenv('FB_PAGE_TOKEN_3')],
    ['id' => getenv('FB_PAGE_ID_4'), 'token' => getenv('FB_PAGE_TOKEN_4')]
];

$inventoryFile = __DIR__ . "/list.json";
$stateFile = __DIR__ . "/state.json";

if (!file_exists($inventoryFile)) {
    die("list.json not found");
}

$state = json_decode(file_get_contents($stateFile), true);
$items = json_decode(file_get_contents($inventoryFile), true);
if (!$items) {
    die("Error reading list.json");
}

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

// 3. Pick the NEXT page in the rotation
$pageIndex = $state['next_index'] % count($fbpages);
$selectedPage = $fbpages[$pageIndex];

$title = $nextItem["title"];
$price = $nextItem["price"];
// Support both 'currency' and 'currencyID' just in case
$currency = $nextItem["currency"] ?? $nextItem["currencyID"] ?? "USD"; 

$itemID = $nextItem['itemID'];
$affiliateUrl = "https://www.ebay.com/itm/{$itemID}?mkevt=1&mkcid=1&mkrid={$rotationID}&campid={$campID}&toolid={$toolID}&customid=facebook_bot";

$imgUrl = $nextItem['imgUrl'];
$dynamicTags = getHashtagsFromTitle($item['title']);
$hashtags = "\n\n#eBayseller #eBayFinds #esquireattire " . $dynamicTags . " #ad";
$message = $item['title'] . "\nPrice: " . $item['price'] . " " . $item['currency'] . "\n\n" . $hashtags . "\n\nLink:";
$pageIndex = $state['next_page_index'] % count($fbpages);
$selectedPage = $fbpages[$pageIndex];
$endpoint = "https://graph.facebook.com/v19.0/{$selectedPage['id']}/photos";
$postData = [
    "message" => $message . "\n\n" . $affiliateUrl, 
    "url" => $imgUrl,
    "access_token" => $selectedPage['token']
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
    echo "Successfully posted to {$selectedPage['id']}\n";
    $atLeastOneSuccess = true; 

    // Save progress
    $items[$nextIndex]["posted"] = true;
    $state['next_index'] = ($pageIndex + 1) % count($fbpages);
    
    file_put_contents($inventoryFile, json_encode($items, JSON_PRETTY_PRINT));
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    echo "\nFinal Status: Item marked as posted.\nPage Index changed.";
    
} else {
    echo "Error posting to Page: " . $response . "\n";
}

function getHashtagsFromTitle($title) {
    // 1. Common words to ignore (add more as you see fit)
    $stopWords = ['the', 'and', 'with', 'for', 'from', 'this', 'that', 'your', 'shipping', 'new', 'used'];
    
    // 2. Clean the title (remove special characters)
    $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', strtolower($cleanTitle));
    
    $tags = [];
    foreach ($words as $word) {
        // Only keep words longer than 3 letters that aren't stop words
        if (strlen($word) > 3 && !in_array($word, $stopWords)) {
            $tags[] = '#' . ucfirst($word);
        }
    }
    
    // 3. Return the first 3 tags as a string
    return implode(' ', array_slice($tags, 0, 3));
}
?>
