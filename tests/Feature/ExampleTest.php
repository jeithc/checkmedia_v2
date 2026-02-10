<?php

test('the application redirects to login', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});
