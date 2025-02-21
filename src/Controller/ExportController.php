<?php

namespace Upload\Controller;


class ExportController
{

    private function handleExport(TcpConnection $connection, Request $request)
    {
        $taskId = uniqid();
        $exportData = json_decode($request->rawBody(), true);

        $this->sendToRedisQueue('export_tasks', [
            'taskId' => $taskId,
            'data' => $exportData
        ]);

        send_json($connection, 200, ' success', ['taskId' => $taskId]);
    }
}