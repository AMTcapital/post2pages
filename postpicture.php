<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$potteryCampID = getenv('Pottery_Camp_ID'); 
$promoCampID = getenv('FRIEND_CAMPAIGN_ID');
$mainCampID = getenv('campID');
$toolID = getenv('toolID');
$rotationID = getenv('rotationID');

$fbpages = [
    ['id' => getenv('FB_PAGE_ID_1'), 'token' => getenv('FB_PAGE_TOKEN_1')],
    ['id' => getenv('FB_PAGE_ID_2'), 'token' => getenv('FB_PAGE_TOKEN_2')],
    ['id' => getenv('FB_PAGE_ID_3'), 'token' => getenv('FB_PAGE_TOKEN_3')],
    ['id' => getenv('FB_PAGE_ID_4'), 'token' => getenv('FB_PAGE_TOKEN_4')]
];

$stateFile = __DIR__ . "/state.json";
$inventoryFile = __DIR__ . "/inv/esq_list.json"; //esquire inventory  exclusing category xxx
$esqPageFile = __DIR__ . "/inv/esq_page.json";   // esqire inventory category xxx
$promoFile = __DIR__ . "/promo/deals_on_all.json";  // promo for luxury 
$potteryFile = __DIR__ . "/promo/pottery.json"; // promo for pottery and china 

if (!file_exists($inventoryFile)) {
    die("list.json not found");
}

$state = json_decode(file_get_contents($stateFile), true);
$nextItem = null;
$nextIndex = null;

// Pick the NEXT page in the rotation
$pageIndex = $state['next_index'] % count($fbpages);
$selectedPage = $fbpages[$pageIndex];

// Select the Item base on the turn of the page 
if ($pageIndex == 2){
    $items = json_decode(file_get_contents($promoFile), true);
    $campID= $promoCampID;
    $activeFile=$promoFile;
}elseif ($pageIndex ==1){
    $items = json_decode(file_get_contents($potteryFile), true);
    $campID = $potteryCampID;
    $activeFile=$potteryFile;
} elseif ($pageIndex == 3){
    $items = json_decode(file_get_contents($esqPageFile), true);
    $campID = $mainCampID;
    $activeFile = $esqPageFile;
}else{
    $items = json_decode(file_get_contents($inventoryFile), true);
    $campID = $mainCampID;
    $activeFile = $inventoryFile;
}

if (!$items) {
    die("Error reading list.json");
}

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
$dynamicTags = getHashtagsFromTitle($nextItem['title']);
$hashtags = "\n\n#eBayseller #eBayFinds #esquireattire " . $dynamicTags . " #ad";
$message = $nextItem['title'] . "\nPrice: " . $nextItem['price'] . " " . $nextItem['currency'] . "\n\nLink: " .$affiliateUrl . "\n\nVisit our eBay store\n\n" . $hashtags ;

$endpoint = "https://graph.facebook.com/v19.0/{$selectedPage['id']}/photos";
$postData = [
    "message" => $message, 
    "url" => $highResImgUrl,
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
    $state["next_index"] = ($pageIndex + 1) % count($fbpages);
    
    file_put_contents($activeFile, json_encode($items, JSON_PRETTY_PRINT));
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
