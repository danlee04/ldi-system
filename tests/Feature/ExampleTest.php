<?php

test('the application answers on its front door', function () {
    // It answers with a redirect now — where it sends people is
    // HomeRedirectTest's business; this only says the app is up.
    $this->get(route('home'))->assertRedirect();
});
