<?php
// Stub of WordPress' dbDelta() for the smoke test: records the CREATE TABLE statements.
function dbDelta($q) { $GLOBALS["ddl"][] = $q; }
