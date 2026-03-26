<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$campID = getenv('campID');
$toolID = getenv('toolID'); 
$rotationID = getenv('rotationID');

$fbpages = [
    ['id' => getenv('FB_PAGE_FBQ_ID'), 'token' => getenv('FB_PAGE_FBQ_TOKEN'), 'file' => 'listfbq.json'],
    ['id' => getenv('FB_PAGE_ESQ_ID'), 'token' => getenv('FB_PAGE_ESQ_TOKEN'), 'file' => 'listesq.json']
];

$stateFile = __DIR__ . "/state.json";
if (!file_exists($stateFile)) {
    // Create a default state if it's missing so the script doesn't crash
    $state = ['next_index_fbq' => 0];
} else {
    $state = json_decode(file_get_contents($stateFile), true);
}

$pageIndex = $state['next_index_fbq']%count($fbpages);
$selectedPage = $fbpages[$pageIndex];
$dataFile = $selectedPage['file'];

$inventoryFile = __DIR__ . "/inv/". $dataFile;


if (!file_exists($inventoryFile)) {
    die( $dataFile . " not found");
}

$items = json_decode(file_get_contents($inventoryFile), true);
if (!$items) {
    die("Error reading " . $dataFile);
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

$title = $nextItem["title"];
$price = $nextItem["price"];
// Support both 'currency' and 'currencyID' just in case
$currency = $nextItem["currency"] ?? $nextItem["currencyID"] ?? "USD"; 

$itemID = $nextItem['itemID'];
$affiliateUrl = "https://www.ebay.com/itm/{$itemID}?mkevt=1&mkcid=1&mkrid={$rotationID}&campid={$campID}&toolid={$toolID}&customid=facebook_bot";

$imgUrl = $nextItem['imgUrl'];
// Force the 1600px high-res version
$highResImgUrl = str_replace('s-l225', 's-l1600', $imgUrl);
$dynamicTags = getHashtagsFromTitle($item['title']);
$hashtags = "\n\n#eBayseller #eBayFinds #esquireattire " . $dynamicTags . " #ad";
$messagePhoto = $item['title'] . "\nPrice: " . $item['price'] . " " . $item['currency'] . "\n\nLink: " .$affiliateUrl . "\n\nVisit our eBay store\n\n" . $hashtags ;
$messageFeed = $item['title'] . "\nPrice: " . $item['price'] . " " . $item['currency'] . "\n\n" . $hashtags ;

$isThirdItem = (($nextIndex + 1)%3===0);

if ($isThirdItem){
// --- FEED ENDPOINT (Status Update with Link) ---
    $endpoint = "https://graph.facebook.com/v19.0/{$selectedPage['id']}/feed";
    $postData = [
        "message" => $messageFeed, 
        "link" => $affiliatedUrl,
        "access_token" => $selectedPage['token']
    ];
    echo "Using FEED endpoint for item index $nextIndex\n";
} else {
    $endpoint = "https://graph.facebook.com/v19.0/{$selectedPage['id']}/photos";
    $postData = [
        "message" => $messagePhoto, 
        "url" => $highResImgUrl,
        "access_token" => $selectedPage['token']
];
    echo "using Photo endpoint for item index $nextIndex\n";
}
  
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
    $state["next_index_fbq"] = ($pageIndex + 1) % count($fbpages);
    
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
