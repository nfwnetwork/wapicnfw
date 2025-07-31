<?php
/*
Plugin Name: Eisleber Wiesenmarkt Game
Description: A simple coin collecting game for the Eisleber Wiesenmarkt.
Version: 1.0.0
Author: Codex
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function ewiesenmarkt_game_enqueue_scripts() {
    wp_enqueue_style('ewiesenmarkt-game-style', plugins_url('style.css', __FILE__));
    wp_enqueue_script('ewiesenmarkt-game-script', plugins_url('game.js', __FILE__), array(), false, true);
}
add_action('wp_enqueue_scripts', 'ewiesenmarkt_game_enqueue_scripts');

function ewiesenmarkt_game_shortcode() {
    return '<div id="ew-game-container"></div>';
}
add_shortcode('wiesenmarkt_game', 'ewiesenmarkt_game_shortcode');
?>
