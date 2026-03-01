<?php
exec('php bin/console debug:asset-map', $output);
file_put_contents('asset_map_debug.txt', implode("\n", $output));
