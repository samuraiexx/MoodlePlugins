<?php

$observers = array(
    array(
      'eventname'   => '\assignsubmission_file\event\assessable_uploaded',
      'callback'    => 'local_codeverify_observer::test'
      )
);
