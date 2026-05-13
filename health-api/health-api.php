<?php
/*
Plugin Name: Health API
*/

add_action('rest_api_init', function () {
    register_rest_route('health/v1', '/diagnosis', array(
        'methods' => 'POST',
        'callback' => 'healthinsight_api',
    ));
});

function healthinsight_api($request){

    $app_id = "a829af2f";
    $app_key = "6d1ca892075d00253b403bb4971fc048";

    $body = $request->get_body();

    $response = wp_remote_post("https://api.infermedica.com/v3/diagnosis", array(
        'headers' => array(
            'App-Id' => $app_id,
            'App-Key' => $app_key,
            'Content-Type' => 'application/json'
        ),
        'body' => $body
    ));

    if (is_wp_error($response)) {
        return array("error" => "API failed");
    }

    return json_decode(wp_remote_retrieve_body($response));
}