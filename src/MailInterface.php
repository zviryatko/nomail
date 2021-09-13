<?php

namespace zviryatko\nomail;

/**
 * Mail sent interface.
 */
interface MailInterface {

  /**
   * Sent mail.
   *
   * @param string $from
   *   Target user.
   * @param string $to
   *   Target user.
   * @param string $subject
   *   Mail subject.
   * @param string $message
   *   Mail text.
   *
   * @return int
   *   Status code: >0 error
   */
  public function mail(string $from, string $to, string $subject, string $message): int;
}
