<?php
declare(strict_types=1);

namespace morfeditorial\screens\Project;

use morfeditorial\screens\AbstractScreen;

class ProjectManageScreen extends AbstractScreen
{
    public function render(): void
    {
        $screen = new ProjectListScreen($this->bot, $this->data);
        $screen->render();
    }
}
