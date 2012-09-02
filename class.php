<?php


class NikeplusFuelbandAPI {
  var $email = '';

  var $password = '';

  var $authenticated = FALSE;

  var $cookie_file = '/tmp/cookiefile';

  function __construct($email = NULL, $password = NULL) {
    if (isset($email) && isset($password)) {
      $this->login($email, $password);
    }
  }

  function is_authenticated() {
    return $authenticated;
  }

  function login($email, $password) {
    $this->email = $email;
    $this->password = $password;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_URL, 'https://secure-nikeplus.nike.com/nsl/services/user/login?app=b31990e7-8583-4251-808f-9dc67b40f5d2&format=json&contentType=plaintext');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, 'email=' . $this->email . '&password=' . $this->password);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie_file);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    //curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie_file);

    $result = curl_exec($curl);
    // @todo: handle errors.
    $curl_errno = curl_errno($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);
    if ($curl_errno == 0) {
      $this->authenticated = TRUE;
    }
    else {
      drupal_set_message($curl_error);
    }
  }

  function get_friend_last_full_activity($friend_name) {
    $url = 'http://nikeplus.nike.com/plus/profile/' . $friend_name;

    $html = $this->request($url);

    $activity = $this->parse_activity($html);
    if ($activity->activity->activityType != "ALL_DAY") {
      $activity = $this->parse_activity($html, 'thirdMostRecentActivity');
      if ($activity->activity->activityType != "ALL_DAY") {
        return FALSE;
      }
    }
    return $activity;
  }

  function parse_activity($html, $activity = 'secondMostRecentActivity') {
    // @todo: refactor this code.
    $words = explode('window.np.' . $activity . ' = ', $html);
    $words = explode('</script>', $words[1]);
    // Remove the ";" from the end of the string.
    $words[0] = substr($words[0], 0, strlen($words[0]) - 2);
    return json_decode($words[0]);
  }

  function request($url) {
    if (!$this->authenticated) {
      return FALSE;
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie_file);
    curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie_file);
    $result = curl_exec($curl);
    // @todo: handle errors.
    $curl_errno = curl_errno($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);
    if ($curl_errno == 0) {
      return $result;
    }
    else {
      drupal_set_message($curl_errno);
      return FALSE;
    }
  }

}
