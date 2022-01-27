<?php

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\attributes\ArrayAttribute;
use ColinHDev\CPlot\attributes\BaseAttribute;
use ColinHDev\CPlot\attributes\utils\AttributeParseException;
use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\player\PlayerData;
use ColinHDev\CPlot\player\settings\SettingManager;
use ColinHDev\CPlot\provider\DataProvider;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class SettingSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : \Generator {
        if (count($args) === 0) {
            $sender->sendMessage($this->getPrefix() . $this->getUsage());
            return;
        }

        switch ($args[0]) {
            case "list":
                $sender->sendMessage($this->getPrefix() . $this->translateString("setting.list.success"));
                $settingsByCategory = [];
                foreach (SettingManager::getInstance()->getSettings() as $setting) {
                    $settingCategory = $this->translateString("setting.category." . $setting->getID());
                    if (!isset($settingsByCategory[$settingCategory])) {
                        $settingsByCategory[$settingCategory] = $setting->getID();
                    } else {
                        $settingsByCategory[$settingCategory] .= $this->translateString("setting.list.success.separator") . $setting->getID();
                    }
                }
                foreach ($settingsByCategory as $category => $settings) {
                    $sender->sendMessage($this->translateString("setting.list.success.format", [$category, $settings]));
                }
                break;

            case "info":
                if (!isset($args[1])) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.info.usage"));
                    break;
                }
                $setting = SettingManager::getInstance()->getSettingByID($args[1]);
                if ($setting === null) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.info.noSetting", [$args[1]]));
                    break;
                }
                $sender->sendMessage($this->getPrefix() . $this->translateString("setting.info.setting", [$setting->getID()]));
                $sender->sendMessage($this->translateString("setting.info.ID", [$setting->getID()]));
                $sender->sendMessage($this->translateString("setting.info.category", [$this->translateString("setting.category." . $setting->getID())]));
                $sender->sendMessage($this->translateString("setting.info.description", [$this->translateString("setting.description." . $setting->getID())]));
                $sender->sendMessage($this->translateString("setting.info.type", [$this->translateString("setting.type." . $setting->getID())]));
                $sender->sendMessage($this->translateString("setting.info.default", [$setting->getDefault()]));
                break;

            case "my":
                if (!$sender instanceof Player) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.my.senderNotOnline"));
                    break;
                }
                $playerData = yield from DataProvider::getInstance()->awaitPlayerDataByUUID($sender->getUniqueId()->getBytes());
                if (!($playerData instanceof PlayerData)) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.my.loadPlayerDataError"));
                    break;
                }
                $settings = $playerData->getSettings();
                if (count($settings) === 0) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.my.noSettings"));
                    break;
                }
                $settings = array_map(
                    function (BaseAttribute $setting) : string {
                        return $this->translateString("setting.my.success.format", [$setting->getID(), $setting->toString()]);
                    },
                    $settings
                );
                $sender->sendMessage(
                    $this->getPrefix() .
                    $this->translateString(
                        "setting.my.success",
                        [implode($this->translateString("setting.my.success.separator"), $settings)]
                    )
                );
                break;

            case "set":
                if (!isset($args[1])) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.usage"));
                    break;
                }

                if (!$sender instanceof Player) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.senderNotOnline"));
                    break;
                }
                $playerData = yield from DataProvider::getInstance()->awaitPlayerDataByUUID($sender->getUniqueId()->getBytes());
                if (!($playerData instanceof PlayerData)) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.loadPlayerDataError"));
                    break;
                }

                /** @var BaseAttribute | null $setting */
                $setting = SettingManager::getInstance()->getSettingByID($args[1]);
                if ($setting === null) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.noSetting", [$args[1]]));
                    break;
                }
                if (!$sender->hasPermission($setting->getPermission())) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.permissionMessageForSetting", [$setting->getID()]));
                    break;
                }

                array_splice($args, 0, 2);
                $arg = implode(" ", $args);
                try {
                    $parsedValue = $setting->parse($arg);
                } catch (AttributeParseException) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.parseError", [$arg, $setting->getID()]));
                    break;
                }

                $setting = $setting->newInstance($parsedValue);
                $oldSetting = $playerData->getSettingByID($setting->getID());
                if ($oldSetting !== null) {
                    $setting = $oldSetting->merge($setting->getValue());
                }
                $playerData->addSetting(
                    $setting
                );
                yield from DataProvider::getInstance()->savePlayerSetting($playerData, $setting);
                $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.success", [$setting->getID(), $setting->toString($parsedValue)]));
                break;

            case "remove":
                if (!isset($args[1])) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.usage"));
                    break;
                }

                if (!$sender instanceof Player) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.senderNotOnline"));
                    break;
                }
                $playerData = yield from DataProvider::getInstance()->awaitPlayerDataByUUID($sender->getUniqueId()->getBytes());
                if (!($playerData instanceof PlayerData)) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.set.loadPlayerDataError"));
                    break;
                }

                /** @var BaseAttribute | null $setting */
                $setting = $playerData->getSettingByID($args[1]);
                if ($setting === null) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.remove.settingNotSet", [$args[1]]));
                    break;
                }
                if (!$sender->hasPermission($setting->getPermission())) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("setting.remove.permissionMessageForSetting", [$setting->getID()]));
                    break;
                }

                array_splice($args, 0, 2);
                if (count($args) > 0 && $setting instanceof ArrayAttribute) {
                    $arg = implode(" ", $args);
                    try {
                        $parsedValues = $setting->parse($arg);
                    } catch (AttributeParseException) {
                        $sender->sendMessage($this->getPrefix() . $this->translateString("setting.remove.parseError", [$arg, $setting->getID()]));
                        break;
                    }

                    $values = $setting->getValue();
                    foreach ($parsedValues as $parsedValue) {
                        $key = array_search($parsedValue, $values, true);
                        if ($key === false) {
                            continue;
                        }
                        unset($values[$key]);
                    }

                    if (count($values) > 0) {
                        $setting = $setting->newInstance($values);
                        $playerData->addSetting($setting);
                        yield from DataProvider::getInstance()->savePlayerSetting($playerData, $setting);
                        $sender->sendMessage($this->getPrefix() . $this->translateString("setting.remove.value.success", [$setting->getID(), $setting->toString()]));
                        break;
                    }
                }
                $playerData->removeSetting($setting->getID());
                yield from DataProvider::getInstance()->deletePlayerSetting($playerData, $setting->getID());
                $sender->sendMessage($this->getPrefix() . $this->translateString("setting.remove.setting.success", [$setting->getID()]));
                break;

            default:
                $sender->sendMessage($this->getPrefix() . $this->getUsage());
                break;
        }
    }

    /**
     * @param \Throwable $error
     */
    public function onError(CommandSender $sender, \Throwable $error) : void {
        if ($sender instanceof Player && !$sender->isConnected()) {
            return;
        }
        $sender->sendMessage($this->getPrefix() . $this->translateString("setting.saveError", [$error->getMessage()]));
    }
}