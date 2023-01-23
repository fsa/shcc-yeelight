<?php

namespace FSA\YeelightPlugin\Devices;

use FSA\YeelightPlugin\Socket;
use FSA\SmartHome\DeviceInterface;
use FSA\SmartHome\Capability\{PowerInterface, ColorHsvInterface, ColorRgbInterface, ColorTInterface, BrightnessInterface};

class LED implements DeviceInterface, PowerInterface, ColorHsvInterface, ColorRgbInterface, ColorTInterface, BrightnessInterface
{
    private $location;
    private $id;
    private $model;
    private $fw_ver;
    private $support;
    private $power;
    private $bright;
    private $color_mode;
    private $ct;
    private $rgb;
    private $hue;
    private $sat;
    private $name;
    private $socket;
    private $updated;
    private $message_id = 1;
    private $events = [];

    public function __construct()
    {
        $this->updated = 0;
    }

    public function __sleep()
    {
        return array('location', 'id', 'model', 'fw_ver', 'support', 'power', 'bright', 'color_mode', 'ct', 'rgb', 'hue', 'sat', 'name', 'updated');
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function init($device_id, $init_data): void
    {
        $this->id = $device_id;
        foreach ($init_data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getInitDataList(): array
    {
        return ['location' => 'IP адрес', 'model' => 'Тип устройства'];
    }

    public function getInitDataValues(): array
    {
        return ['location' => $this->location, 'model' => $this->model];
    }

    public function updateState($params)
    {
        foreach ($params as $param => $value) {
            switch ($param) {
                case "Location":
                    $this->location = $value;
                    break;
                case "id":
                    $this->id = $value;
                    break;
                case "model":
                    $this->model = $value;
                    break;
                case "fw_ver":
                    $this->fw_ver = $value;
                    break;
                case "support":
                    $this->support = explode(' ', $value);
                    break;
                case "power":
                    $this->setPowerValue($value);
                    break;
                case "bright":
                    $this->setBrightValue($value);
                    break;
                case "color_mode":
                    $this->setColorModeValue($value);
                    break;
                case "ct":
                    $this->setCTValue($value);
                    break;
                case "rgb":
                    $this->setRGBValue(dechex($value));
                    break;
                case "hue":
                    $this->setHueValue($value);
                    break;
                case "sat":
                    $this->setSatValue($value);
                    break;
                case "name":
                    $this->name = $value;
                    break;
                default:
            }
        }
        $this->updated = time();
    }

    private function setPowerValue($value)
    {
        if ($this->power == $value) {
            return;
        }
        $this->power = $value;
        $this->events['power'] = $value;
    }

    private function setBrightValue($value)
    {
        if ($this->bright == $value) {
            return;
        }
        $this->bright = $value;
        $this->events['bright'] = $value;
    }

    private function setColorModeValue($value)
    {
        if ($this->color_mode == $value) {
            return;
        }
        $this->color_mode = $value;
        $this->events['color_mode'] = $value;
    }

    private function setCTValue($value)
    {
        if ($this->ct == $value) {
            return;
        }
        $this->ct = $value;
        $this->events['ct'] = $value;
    }

    private function setRGBValue($value)
    {
        if ($this->rgb == $value) {
            return;
        }
        $this->rgb = $value;
        $this->events['rgb'] = $value;
    }

    private function setHueValue($value)
    {
        if ($this->hue == $value) {
            return;
        }
        $this->hue = $value;
        $this->events['hue'] = $value;
    }

    private function setSatValue($value)
    {
        if ($this->sat == $value) {
            return;
        }
        $this->sat = $value;
        $this->events['sat'] = $value;
    }

    private function getSocket()
    {
        if (!is_null($this->socket)) {
            return $this->socket;
        }
        $addr = parse_url($this->location);
        $socket = @stream_socket_client("tcp://" . $addr['host'] . ":" . $addr['port'], $errno, $errstr);
        if (!$socket) {
            switch ($errno) {
                case 113:
                    throw new Exception("Устройство недоступно.");
                default:
                    throw new Exception($errstr, $errno);
            }
        }
        stream_set_timeout($socket, 3);
        $this->socket = $socket;
        return $socket;
    }

    public function getEffect(int $duration)
    {
        if ($duration > 0 and $duration < 30) {
            throw new Exception('Длительность эффекта должна быть больше 30.');
        }
        return ($duration == 0) ? 'sudden' : 'smooth';
    }

    public function sendGetProp(array $params): int
    {
        return $this->sendCommand('get_prop', $params);
    }

    public function sendSetCtAbx(int $ct_value, int $duration = 0): int
    {
        return $this->sendCommand('set_ct_abx', [$ct_value, $this->getEffect($duration), $duration]);
    }

    /**
     * 
     * @param int $ct_value цветовая температура (1700-6500К)
     * @param int $duration
     * @return int
     */
    public function sendBgSetCtAbx(int $ct_value, int $duration = 0): int
    {
        return $this->sendCommand('bg_set_ct_abx', [$ct_value, $this->getEffect($duration), $duration]);
    }

    public function sendSetRGB(string $rgb, int $duration = 0): int
    {
        return $this->sendCommand('set_rgb', [hexdec($rgb), $this->getEffect($duration), $duration]);
    }

    public function sendBgSetRGB(string $rgb, int $duration = 0): int
    {
        return $this->sendCommand('bg_set_rgb', [hexdec($rgb), $this->getEffect($duration), $duration]);
    }

    public function sendSetHSV(int $hue, int $sat, int $duration = 0): int
    {
        return $this->sendCommand('set_hsv', [$hue, $sat, $this->getEffect($duration), $duration]);
    }

    public function sendBgSetHSV(int $hue, int $sat, int $duration = 0): int
    {
        return $this->sendCommand('bg_set_hsv', [$hue, $sat, $this->getEffect($duration), $duration]);
    }

    public function sendSetBright(int $bright, int $duration = 0): int
    {
        return $this->sendCommand('set_bright', [$bright, $this->getEffect($duration), $duration]);
    }

    public function sendBgSetBright(int $bright, int $duration = 0): int
    {
        return $this->sendCommand('bg_set_bright', [$bright, $this->getEffect($duration), $duration]);
    }

    public function sendSetPower(bool $on, int $duration = 0, $mode = 0): int
    {
        $param = [];
        $param[] = $on ? 'on' : 'off';
        $param[] = $this->getEffect($duration);
        $param[] = $duration;
        if ($mode != 0) {
            $param[] = $mode;
        }
        return $this->sendCommand('set_power', $param);
    }

    public function sendBgSetPower(bool $on, int $duration = 0, $mode = 0): int
    {
        $param = [];
        $param[] = $on ? 'on' : 'off';
        $param[] = $this->getEffect($duration);
        $param[] = $duration;
        if ($mode != 0) {
            $param[] = $mode;
        }
        return $this->sendCommand('bg_set_power', $param);
    }

    public function sendToggle(): int
    {
        return $this->sendCommand('toggle');
    }

    public function sendBgToggle(): int
    {
        return $this->sendCommand('bg_toggle');
    }

    public function sendSetDefault(): int
    {
        return $this->sendCommand('set_default');
    }

    public function sendBgSetDefault(): int
    {
        return $this->sendCommand('bg_set_default');
    }

    public function sendStartCF(int $count, int $action, string $flow_expression): int
    {
        return $this->sendCommand('start_cf', [$count, $action, $flow_expression]);
    }

    public function sendBgStartCF(int $count, int $action, string $flow_expression): int
    {
        return $this->sendCommand('bg_start_cf', [$count, $action, $flow_expression]);
    }

    /**
     * flow_expression helper
     * @param int $duration
     * @param int $mode
     * @param string $value
     * @param int $brightness
     * @return string
     * @throws Exception
     */
    public function changingState(int $duration, int $mode, string $value, int $brightness): string
    {
        if ($mode <> 1 and $mode <> 2 and $mode <> 7) {
            throw new Exception('Mode error: 1-color, 2-color temperature, 7-sleep');
        }
        $dig_value = ($mode == 1) ? hexdec($value) : (int) $value;
        return "$duration,$mode,$dig_value,$brightness";
    }

    public function sendStopCF(): int
    {
        return $this->sendCommand('stop_cf');
    }

    public function sendBgStopCF(): int
    {
        return $this->sendCommand('bg_stop_cf');
    }

    public function sendSetScene(array $params): int
    {
        return $this->sendCommand("set_scene", $params);
    }

    public function sendBgSetScene(array $params): int
    {
        return $this->sendCommand("bg_set_scene", $params);
    }

    public function sendCronAdd(int $type, int $value): int
    {
        return $this->sendCommand('cron_add', [$type, $value]);
    }

    public function sendCronGet(int $type): int
    {
        return $this->sendCommand('cron_get', [$type]);
    }

    public function sendCronDel(int $type): int
    {
        return $this->sendCommand('cron_del', [$type]);
    }

    public function sendSetAdjust(string $action, string $prop): int
    {
        return $this->sendCommand('set_adjust', [$action, $prop]);
    }

    public function sendBgSetAdjust(string $action, string $prop): int
    {
        return $this->sendCommand('bg_set_adjust', [$action, $prop]);
    }

    public function sendSetMusic(string $host = '', int $port = 0): int
    {
        if ($host == '') {
            return $this->sendCommand('set_music', [0]);
        } else {
            return $this->sendCommand('set_music', [1, $host, $port]);
        }
    }

    public function sendSetName(string $name): int
    {
        return $this->sendCommand('set_name', [$name]);
    }

    public function sendDevToggle(): int
    {
        return $this->sendCommand('dev_toggle');
    }

    private function sendCommand(string $method, array $params = []): int
    {
        $id = $this->message_id++;
        $cmd = [
            'id' => $id,
            'method' => $method,
            'params' => $params
        ];
        $cmd = json_encode($cmd) . "\r\n";
        $socket = $this->getSocket();
        stream_socket_sendto($socket, $cmd);
        return $id;
    }

    public function getResponse(): string
    {
        return stream_get_contents($this->socket);
    }

    public function disconnect(): void
    {
        if (!is_null($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getLastUpdate(): int
    {
        return $this->updated;
    }

    public function getModuleName(): string
    {
        return 'yeelight';
    }

    public function getHwid(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        switch ($this->model) {
            case "lamp":
                return "Настольная лампа";
            case "mono":
                return "Лампа";
            case "color":
                return "RGB-лампа";
            case "stripe":
                return "Светодиодная лента";
            case "ceiling":
                return "Потолочный светильник";
            case "bslamp":
            case "bslamp1":
                return "Прикроватный светильник";
            default:
                return $this->model;
        }
    }

    public function getState(): array
    {
        $this->refreshState();
        # Учесть режим работы с цветом
        return [
            'power' => $this->getPowerValue(),
            'bright' => $this->getBrightValue(),
            'ct' => $this->getCTValue()
        ];
    }

    public function __toString(): string
    {
        $state = $this->getState();
        return 'Состояние: ' . ($state['power'] ? 'включено, яркость ' . $state['bright'] . '%' : 'выключено');
    }

    public function getSupportValue(): array
    {
        return $this->support;
    }

    public function getPowerValue(): bool
    {
        return $this->power == 'on';
    }

    public function getBrightValue(): int
    {
        return intval($this->bright);
    }

    public function getColorModeValue(): int
    {
        return intval($this->color_mode);
    }

    public function getCTValue(): int
    {
        return intval($this->ct);
    }

    public function getRGBValue(): int
    {
        return intval($this->rgb);
    }

    public function getHueValue(): int
    {
        return intval($this->hue);
    }

    public function getSatValue(): int
    {
        return intval($this->sat);
    }

    public function getNameValue(): string
    {
        return $this->name;
    }

    public function refreshState(): void
    {
        if (is_null($this->location)) {
            # Не инициализирован IP адрес устройства
            return;
        }
        $ip = parse_url($this->location, PHP_URL_HOST);
        $yeelight = new Socket();
        $yeelight->sendDiscover($ip);
        $packet = $yeelight->waitPacket($ip);
        if ($packet) {
            $this->updateState($packet->getParams());
        }
    }

    public function setPower(bool $value, $line = 0)
    {
        if ($line == 0) {
            $this->sendSetPower($value, 300);
        } else {
            $this->sendBgSetPower($value, 300);
        }
    }

    public function setPowerOff($line = 0)
    {
        if ($line == 0) {
            $this->sendSetPower(false, 300);
        } else {
            $this->sendBgSetPower(false, 300);
        }
    }

    public function setPowerOn($line = 0)
    {
        if ($line == 0) {
            $this->sendSetPower(true, 300);
        } else {
            $this->sendBgSetPower(true, 300);
        }
    }

    public function getPower(int $line = 0): bool
    {
        return $this->getPowerValue();
    }

    public function setCT(int $ct_value, int $line = 0)
    {
        if ($line == 0) {
            $this->sendSetCtAbx($ct_value);
        } else {
            $this->sendBgSetCtAbx($ct_value);
        }
    }

    public function getCT(int $line = 0): int
    {
        return $this->getCTValue();
    }

    public function setHSV($hue, $sat, $value, int $line = 0)
    {
        if ($line == 0) {
            $this->sendSetHSV($hue, $sat);
        } else {
            $this->sendSetBright($value);
        }
    }

    public function getHSV(int $line = 0): array
    {
        return [$this->getHueValue(), $this->getSatValue(), $this->getBrightValue()];
    }

    public function setRGB(int $rgb, int $line = 0)
    {
        if ($line == 0) {
            $this->sendSetRGB(dechex($rgb));
        } else {
            $this->sendBgSetRGB(dechex($rgb));
        }
    }

    public function getRGB(int $line = 0): int
    {
        return $this->getRGBValue();
    }

    public function setBrightness(int $brightness, int $line = 0): void
    {
        if ($line == 0) {
            $this->sendSetBright($brightness);
        } else {
            $this->sendBgSetBright($brightness);
        }
    }

    public function getBrightness(int $line = 0): int
    {
        return $this->getBrightValue();
    }

    public function getEvents(): ?array
    {
        if (sizeof($this->events) == 0) {
            return null;
        }
        $events = $this->events;
        $this->events = [];
        return $events;
    }

    public function getEventsList(): array
    {
        return [];
    }
}
