<?php
if ( class_exists('\\KHM\\GEO\\SuggestAnswerCardsEndpoint') ) {
    try {
        $ep = new \\KHM\\GEO\\SuggestAnswerCardsEndpoint();
        $ep->register();
        echo "registered\n";
    } catch ( Throwable $e ) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString();
    }
} else {
    echo "class_missing\n";
}
