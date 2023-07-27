<?php

namespace app\common\service;

use think\facade\Config;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use think\Response;
use think\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class Excel
{
    protected $config = [
        'ext' => 'xlsx',
    ];

    protected $contentType;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, Config::get('excel', []), $config);

        if ($this->config['ext'] === 'xls') {
            $this->contentType = 'application/vnd.ms-excel';
        } elseif ($this->config['ext'] === 'xlsx') {
            $this->contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            throw new Exception('不支持excel类型');
        }
    }

    public function export(array $data, array $head, string $title = ''): Response
    {
        $spreadsheet = new Spreadsheet();

        // Set document properties
        // $spreadsheet->getProperties()->setCreator($this->creator);

        if (!empty($head)) {
            array_unshift($data, $head);
        }
        $spreadsheet->getActiveSheet()->fromArray($data);
        $i = 1;
        $j = 1;

        foreach ($data as $value) {
            foreach ($value as $val) {
                if (is_string($val)) {
                    if (in_array(strrchr($val, '.'), ['.png', '.gif', '.jpg', 'jpeg']) && file_exists($val)) {
                        $spreadsheet->getActiveSheet()
                            ->getCell(Coordinate::stringFromColumnIndex($j) . (string)$i)
                            ->setValue('');

                        $drawing = new Drawing;
                        $drawing->setPath($val); //图片路径
                        $drawing->setHeight(130); //图片高
                        $drawing->setWidth(100);  //图片宽
                        $drawing->setOffsetX(5);//设置图片偏移量
                        $spreadsheet->getActiveSheet()->getRowDimension($i)->setRowHeight(100);//图片所在单元格高度
                        $drawing->setCoordinates(Coordinate::stringFromColumnIndex($j) . (string)$i);
                        $drawing->setWorksheet($spreadsheet->getActiveSheet());
                    } else if (is_numeric($val)) {
                        $spreadsheet->getActiveSheet()
                            ->getCell(Coordinate::stringFromColumnIndex($j) . (string)$i)
                            ->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else {
                        $spreadsheet->getActiveSheet()
                            ->getStyle(Coordinate::stringFromColumnIndex($j) . (string)$i)
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }
                }
                $j++;
            }
            $j = 1;
            $i++;
        }

        $spreadsheet->getActiveSheet()->setTitle($title);
        $spreadsheet->setActiveSheetIndex(0);

        if (request()->isAjax()) {
            $helper = new Sample;
            $helper->write($spreadsheet, $title);
            return download($helper->getFilename($title, $this->config['ext']), sprintf("%s.%s", $title, $this->config['ext']));
        }

        ob_start();
        $writer = IOFactory::createWriter($spreadsheet, ucfirst($this->config['ext']));
        $writer->save('php://output');
        $result = Response::create(ob_get_contents(), 'file')->isContent()->name($title . '.' . $this->config['ext'])->contentType($this->contentType);
        ob_clean();
        return $result;
    }

    public function import(string $fileName): array
    {
        $inputFileType = IOFactory::identify($fileName);
        $reader = IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(true);
        $spreadsheet = $reader->load($fileName);

        return $spreadsheet->getActiveSheet()->toArray();
    }

    /**
     * 快速导出excel
     * @param array|object $cursor 数据(可为游标查询结果-生成器对象)
     * @param array $head 数据头
     * @param string 文件名
     * @return Response
     */
    public function quickExport($cursor, array $head = [], string $title = '')
    {
        ob_start();
        if (!empty($head)) {
            foreach ($head as $v) {
                echo iconv("UTF-8", "GBK//IGNORE", $v);
                echo "\t";
            }
            echo "\n";
        }

        if (!empty($cursor)) {
            foreach ($cursor as $val) {
                foreach (array_keys($head) as $k) {
                    echo iconv("UTF-8", "GBK//IGNORE", str_replace(array("\r\n", "\r", "\n"), "", $val[$k]));
                    echo "\t";
                }
                echo "\n";
            }
        }

        $data = ob_get_contents();
        ob_clean();
        return Response::create($data, 'file')->isContent()->name($title . '.' . $this->config['ext'])->contentType($this->contentType);
    }
}
