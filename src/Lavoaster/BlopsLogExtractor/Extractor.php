<?php namespace Lavoaster\BlopsLogExtractor;

class Extractor
{
    /**
     * @var string
     */
    private $file;

    /**
     * Initialises the Extractor class
     *
     * @param $file
     */
    public function __construct($file){

        $this->file = $file;
    }

    /**
     * Parses the log file and returns a formatted array
     *
     * @return array
     */
    public function parse()
    {
        $lines = explode("\n", $this->file);

        $currentGame = 0;
        $gameData = [];

        foreach($lines as $event) {
            $time = (int) substr($event, 0, 10);
            $eventData = substr($event, 11);

            // Skip these lines
            if ($eventData == '------------------------------------------------------------') continue;

            // Track when the game has started and ignore extra InitGame entries
            if (strpos($eventData, 'InitGame') === 0 && (!isset($gameData[$currentGame]['began']) || $gameData[$currentGame]['began'] != $time)) {
                $currentGame++;
                $gameData[$currentGame]['began'] = $time;
                $settings = $this->getSettings($eventData);
                $gameData[$currentGame]['gametype'] = $settings['g_gametype'];
                $gameData[$currentGame]['map'] = $settings['mapname'];

                unset($settings['g_gametype'], $settings['mapname']);

                $gameData[$currentGame]['settings'] = $settings;
                continue;
            }

            if (strpos($eventData, 'ShutdownGame') === 0) {
                $gameData[$currentGame]['finished'] = $time;
                continue;
            }

            $data = [
                'time' => $time
            ];

            if (strpos($eventData, ';') !== false) {
                $action = $this->parseEvent($eventData);

                if ($action) {
                    $data = $data + $action;
                }
            }

            if(isset($data['type'])) {
                $gameData[$currentGame]['events'][] = $data;
            }
        }

        return array_values($gameData);
    }

    private function parseEvent($line)
    {
        $line = explode(';', $line);

        $data = [];

        $type = $line[0];

        if ($type == 'K') {
            $data = [
                'type' => 'kill',
                'data' => [
                    'player_id' => $line[1],
                    'player_slot' => $line[2],
                    'player_team' => $line[3],
                    'player_name' => $line[4],
                    'killed_player_id' => $line[5],
                    'killed_player_slot' => $line[6],
                    'killed_player_team' => $line[7],
                    'killed_player_name' => $line[8],
                    'weapon' => $line[9],
                    'damage' => $line[10],
                    'type' => $line[11],
                    'target_area' => $line[12],
                ]
            ];
        } elseif ($type == 'D') {
            $data = [
                'type' => 'death',
                'data' => [
                    'killed_player_id' => $line[1],
                    'killed_player_slot' => $line[2],
                    'killed_player_team' => $line[3],
                    'killed_player_name' => $line[4],
                    'player_id' => $line[5],
                    'player_slot' => $line[6],
                    'player_team' => $line[7],
                    'player_name' => $line[8],
                    'weapon' => $line[9],
                    'damage' => $line[10],
                    'type' => $line[11],
                    'target_area' => $line[12],
                ]
            ];
        } elseif ($type == 'J') {
            $data = [
                'type' => 'join',
                'data' => [
                    'player_id' => $line[1],
                    'player_slot' => $line[2],
                    'player_name' => $line[3],
                ]
            ];
        } elseif ($type == 'Q') {
            $data = [
                'type' => 'join',
                'data' => [
                    'player_id' => $line[1],
                    'player_slot' => $line[2],
                    'player_name' => $line[3],
                ]
            ];
        } elseif ($type == 'Weapon') {
            $data = [
                'type' => 'weaponchange',
                'data' => [
                    'player_id' => $line[1],
                    'player_slot' => $line[2],
                    'player_name' => $line[3],
                    'weapon' => $line[4],
                ]
            ];
        } elseif ($type == 'say' || $type == 'sayteam') {
            $data = [
                'type' => 'message',
                'data' => [
                    'player_id' => $line[1],
                    'player_slot' => $line[2],
                    'player_name' => $line[3],
                    'message' => $line[4],
                ]
            ];
        }


        return $data;
    }

    private function getSettings($mapData)
    {
        $settings = [];

        $rawSettings = explode('\\', ltrim($mapData, 'InitGame: \\'));
        $totalSettings = count($rawSettings);

        for ($i = 0; $i < $totalSettings; $i+=2) {
            $settings[$rawSettings[$i]] = $rawSettings[$i+1];
        }

        return $settings;
    }
}