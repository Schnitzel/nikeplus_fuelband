<?php

/**
 * @file
 * Implements an API for the Nikeplus website.
 *
 * Currently, it just parses the html on the website. When there will be a true
 * API from the Nikeplus app, then the implementation of this class will be
 * changed to use that API.
 */

class NikeplusFuelbandAPI {

  /**
   * @var string
   * The email of the main account.
   */
  private $email = '';

  /**
   * @var string
   * The password of the main account.
   */
  private $password = '';

  /**
   * @var string
   * The username of the main account
   */
  private $username = '';

  /**
   * @var bool
   * Flag that indicates if the current object is authenticated or not.
   */
  private $authenticated = FALSE;

  /**
   * @const
   * The path where to store the cookie information. The apache user must have
   * write access to that file.
   */
  const cookie_file = '/tmp/cookiefile';

  /**
   * @const
   * The base url path of the profiles.
   */
  const base_profile_url = 'http://nikeplus.nike.com/plus/';

  public function __construct($email, $password) {
    $this->email = $email;
    $this->password = $password;
  }

  /**
   * @return bool
   *   Checks if the user is authenticated or not.
   */
  public function isAuthenticated() {
    return $this->authenticated;
  }

  /**
   * Logs in the user.
   */
  public function login() {
    // If already authenticated, skip it.
    if ($this->isAuthenticated()) {
      return;
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_URL, 'https://secure-nikeplus.nike.com/nsl/services/user/login?app=b31990e7-8583-4251-808f-9dc67b40f5d2&format=json&contentType=plaintext');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, 'email=' . $this->email . '&password=' . $this->password);
    curl_setopt($curl, CURLOPT_COOKIEJAR, NikeplusFuelbandAPI::cookie_file);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    $result = curl_exec($curl);
    $curl_errno = curl_errno($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);
    if ($curl_errno == 0) {
      $result = json_decode($result);
      $this->authenticated = TRUE;
      $this->username = $result->serviceResponse->body->User->screenName;
    }
    else {
     // @todo: handle errors here?
    }
  }

  /**
   * Returns the last full activity of a friend.
   *
   * It checks only for activities of type "ALL_DAY".
   *
   * @param string $friend_name
   *   The nikeplus profile name.
   * @return bool|object
   *   If the activity was found, it will return an object containing the
   *   information about that activity. Otherwise, returns FALSE.
   */
  public function getFriendLastFullActivity($friend_name) {
    // Make sure we are logged in.
    $this->login();
    // If not yet authenticated, something is wrong, so just return FALSE.
    // The reason why the user is not authenticated should be handled by the
    // login method.
    if (!$this->isAuthenticated()) {
      return FALSE;
    }
    $url = NikeplusFuelbandAPI::base_profile_url . 'profile/' . $friend_name;
    $html = $this->request($url);

    $activity = $this->searchActivity($html, 'secondMostRecentActivity');
    if ($activity->activity->activityType != "ALL_DAY") {
      $activity = $this->searchActivity($html, 'thirdMostRecentActivity');
      if ($activity->activity->activityType != "ALL_DAY") {
        return FALSE;
      }
    }
    return $activity;
  }

  /**
   * Returns the last full activity for own user account.
   *
   * @param int $week_offset
   *   The offset of the current week to search in. Used for recursion.
   *
   * @param bool $monday_had_fuelpoints
   *   If monday of next week of requested week had fuelpoints.
   *   Used for recursion. Defaults to FALSE.
   *
   * @return bool|object
   *   If the activity was found, it will return an object containing the
   *   information about that activity. Otherwise, returns FALSE.
   */
  public function getMyLastFullActivity($week_offset = 0, $monday_had_fuelpoints = FALSE) {
   // Make sure we are logged in.
    $this->login();
    // If not yet authenticated, something is wrong, so just return FALSE.
    // The reason why the user is not authenticated should be handled by the
    // login method.
    if (!$this->isAuthenticated()) {
      return FALSE;
    }

    // Load the timestamp from monday of the requested week.
    if ($week_offset == 0) {
      $monday_of_week_timestamp = strtotime('monday this week');
    }
    else {
      $monday_of_week_timestamp = strtotime('- ' . $week_offset . ' week', strtotime('monday this week'));
    }

    // Build the Year, Month and Monday strings for GMT for requested week.
    $year = gmdate("Y", $monday_of_week_timestamp);
    $month = gmdate("n", $monday_of_week_timestamp);
    $monday = gmdate("j", $monday_of_week_timestamp);

    // Build URL for request
    $url = NikeplusFuelbandAPI::base_profile_url . 'activity/fuelband/' . $this->username . '/week/' . $year . '/' . $month . '/' . $monday;

    $html = $this->request($url);

    $activities = $this->searchActivity($html, 'baked_data');

    // If $monday_had_fuelpoints == TRUE we were called recursively and the next day
    // (Monday next week) has fuelpoints, so we can directly return the last day of
    // this week (Sunday).
    if ($monday_had_fuelpoints) {
      return $activities->items[6];
    }

    // $activities always contains a full week. We go backwards through it and search
    // for an activity with fuelpoints.
    for ($i=6; $i >= 0; $i--) {
      if ($activities->items[$i]->value != 0 && $i == 0) {
        // Found activity with fuelpoints but we are already on the Monday of the week.
        // But because we cannot return one activity earlier, we load one week
        // earlier and tell the function that Monday has fuelpoints.
        return $this->getMyLastFullActivity($week_offset + 1, TRUE);

      }
      elseif ($activities->items[$i]->value != 0 && $i != 0) {
        // Found activity with fuelpoints and we are not on Monday.
        // Return one activity older than the found one.
        return $activities->items[$i-1];
      }
    }
    // No activity with fuelpoints this week, we load one week earlier.
    return $this->getMyLastFullActivity($week_offset + 1, FALSE);
  }

  /**
   * Parses an html string to return the details of a specific activity.
   *
   * @param string $html
   *   The html to search in.
   * @param string $activity
   *   What activity (javascript object name) to search for,
   *   'window.np.' will be prefixed.
   * @return object
   *   A object with the details of the activity.
   */
  private function searchActivity($html, $activity) {
    // @todo: refactor this code.
    $words = explode('window.np.' . $activity . ' = ', $html);
    $words = explode('</script>', $words[1]);
    // Remove the ";" from the end of the string.
    $words[0] = substr($words[0], 0, strlen($words[0]) - 2);
    return json_decode($words[0]);
  }

  /**
   * Performs a request on the nikeplus site.
   *
   * @param string $url
   *   The url used in the request.
   * @return bool|string
   *   If the request was successful,returns the html. Otherwise, returns FALSE.
   */
  private function request($url) {
    // @todo: should we try to authenticate the user if it is not yet
    // authenticated?
    if (!$this->authenticated) {
      return FALSE;
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_COOKIEJAR, NikeplusFuelbandAPI::cookie_file);
    curl_setopt($curl, CURLOPT_COOKIEFILE, NikeplusFuelbandAPI::cookie_file);
    $result = curl_exec($curl);
    // @todo: handle errors.
    $curl_errno = curl_errno($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);
    if ($curl_errno == 0) {
      return $result;
    }
    else {
      // @todo: handle errors here?
      return FALSE;
    }
  }

}
