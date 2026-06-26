<?php

/*
 *
 *    _______   _______    _______   _______
 *   /       \\/       \\//       \//       \
 *  /        //        ///        //      __/
 * /         /         /        _/        _/
 * \__/__/__/\________/\____/___/\_______/
 *
 * This program is licensed under the CSSM Unlimited License v2.0.
 * Copyright (c) 2024 Serhii Cherneha
 *
 * @author CSSM Group
 * @link https://cssm.pp.ua/
 *
 *
 */
declare(strict_types=1);

namespace morfeditorial\screens\Project;

use morfeditorial\screens\AbstractScreen;

class ProjectManageScreen extends AbstractScreen
{
    public function render() : void
    {
        $screen = new ProjectListScreen($this->bot, $this->data);
        $screen->render();
    }
}
