<?php

/**
 *  Excel 导出
 */

namespace Upload\Helper;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Exception;


class Excel
{
    public function outPut($data, $columns, $table = '导出文件')
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 设置第一栏的标题
            foreach ($columns as $k => $v) {
                $sheet->setCellValue($k . "1", $v['title']);
            }

            //第二行起 设置内容
            $baseRow = 2;
            $chunkSize = 1000; // 分批处理数据
            $chunks = array_chunk($data, $chunkSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $key => $value) {
                    $rowIndex = $key + $baseRow;
                    foreach ($columns as $k1 => $v1) {
                        $pValue = isset($value[$v1['field']]) ? trim($value[$v1['field']]) : '';
                        $sheet->setCellValue($k1 . $rowIndex, $pValue);
                    }
                    if (!empty($value['log_str'])) {
                        $num = substr_count($value['log_str'], "\r\n");
                        $sheet->getRowDimension($rowIndex)->setRowHeight(40 * $num);
                    }
                }
            }

            $writer = new Xlsx($spreadsheet);
            $filename = $table . '_' . date("Ymd_His") . '.xlsx';
            $filePath = ROOT_PATH . 'public/excel/' . $filename;
            
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0777, true);
            }
            
            $writer->save($filePath);
            return '/excel/' . $filename;
        } catch (\Exception $e) {
            throw new \Exception('导出Excel失败：' . $e->getMessage());
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function importExcel($file = '', $sheet = 0, $columnCnt = 0, &$options = [])
    {
        try {
            $file = iconv("utf-8", "gb2312", $file);

            if (empty($file) or !file_exists($file)) {
                throw new \Exception('文件不存在!');
            }

            $objRead = IOFactory::createReader('Xlsx');

            if (!$objRead->canRead($file)) {
                $objRead = IOFactory::createReader('Xls');

                if (!$objRead->canRead($file)) {
                    throw new \Exception('只支持导入Excel文件！');
                }
            }

            /* 如果不需要获取特殊操作，则只读内容，可以大幅度提升读取Excel效率 */
            empty($options) && $objRead->setReadDataOnly(true);
            /* 建立excel对象 */
            $obj = $objRead->load($file);

            /* 获取指定的sheet表 */
            $currSheet = $obj->getSheet($sheet);
            //$currSheet = $obj->getSheetByName($sheet);      // 根据名字

            if (isset($options['mergeCells'])) {
                /* 读取合并行列 */
                $options['mergeCells'] = $currSheet->getMergeCells();
            }

            if (0 == $columnCnt) {
                /* 取得最大的列号 */
                $columnH = $currSheet->getHighestColumn();
                /* 兼容原逻辑，循环时使用的是小于等于 */
                $columnCnt = Coordinate::columnIndexFromString($columnH);
            }

            /* 获取总行数 */
            $rowCnt = $currSheet->getHighestRow();
            $data = [];

            /* 读取内容 */
            for ($_row = 1; $_row <= $rowCnt; $_row++) {
                $isNull = true;

                for ($_column = 1; $_column <= $columnCnt; $_column++) {
                    $cellName = Coordinate::stringFromColumnIndex($_column);
                    $cellId = $cellName . $_row;
                    $cell = $currSheet->getCell($cellId);

                    if (isset($options['format'])) {
                        /* 获取格式 */
                        $format = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        /* 记录格式 */
                        $options['format'][$_row][$cellName] = $format;
                    }

                    if (isset($options['formula'])) {
                        /* 获取公式，公式均为=号开头数据 */
                        $formula = $currSheet->getCell($cellId)->getValue();

                        if (0 === strpos($formula, '=')) {
                            $options['formula'][$cellName . $_row] = $formula;
                        }
                    }

                    if (isset($format) && 'm/d/yyyy' == $format) {
                        /* 日期格式翻转处理 */
                        $cell->getStyle()->getNumberFormat()->setFormatCode('yyyy/mm/dd');
                    }

                    $data[$_row][$cellName] = trim($currSheet->getCell($cellId)->getFormattedValue());

                    if (!empty($data[$_row][$cellName])) {
                        $isNull = false;
                    }
                }

                if ($isNull) {
                    unset($data[$_row]);
                }
            }

            return $data;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 格式化指定列数据(默认第一行表头)
    public static function formattingCells(array $data, array $cellConfig)
    {
        $res = array_values($data);

        // 表头
        $header = $res[0];

        $cellKeys = [];
        foreach ($header as $key => $value) {
            foreach ($cellConfig as $k => $v) {
                if ($value == $v) {
                    $cellKeys[$key] = $k;
                }
            }
        }

        if (count($cellKeys) != count($cellConfig)) {
            throw new Exception('表格不完整');
        }

        // 需要添加过滤
        $temp = [];
        for ($i = 1; $i <= count($res) - 1; $i++) {
            foreach ($cellKeys as $m => $n) {
                $temp[$i][$n] = $res[$i][$m];
            }
        }

        return array_values($temp);
    }

    public static  function outPutCsv($rows, $columns)
    {


        $output = fopen("long.csv", "w");
        //UTF8 csv文件头前需添加BOM，不然会是乱码
        fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $columns);
        //数据内容
        foreach ($rows as $row) {
            $item = array_values($row);
            fputcsv($output, $item);
        }
        fclose($output);
    }
}
