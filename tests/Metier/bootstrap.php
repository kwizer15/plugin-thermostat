<?php

function setMetierNow($_datetime) {
	file_put_contents(getenv('FAKETIME_TIMESTAMP_FILE'), $_datetime);
}
