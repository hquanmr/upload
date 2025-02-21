<?php

namespace Upload\Controller;


class DownloadController
{
    private function handleDownload(TcpConnection $connection, Request $request)
    {
        $filename = basename($request->path());
        if (empty($filename)) {
            return send_json($connection, 400, 'Invalid filename');
        }

        $filePath = __DIR__ . '/../../exports/' . $filename;

        if (file_exists($filePath)) {
            $connection->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $connection->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->streamFile($connection, $filePath);
        } else {
            send_json($connection, 404, 'File not found');
        }
    }
}