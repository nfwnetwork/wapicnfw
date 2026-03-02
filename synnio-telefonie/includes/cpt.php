<?php
if (!defined('ABSPATH')) exit;

add_action('init', function(){
  register_post_type('synnio_call', [
    'labels' => [
      'name'          => 'Synnio Calls',
      'singular_name' => 'Synnio Call',
    ],
    'public'       => false,
    'show_ui'      => true,
    'show_in_menu' => true,
    'menu_icon'    => 'dashicons-phone',
    'supports'     => ['title','custom-fields','editor'],
  ]);
});
