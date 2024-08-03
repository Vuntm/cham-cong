<?php
require 'vendor/autoload.php'; // Đảm bảo đường dẫn chính xác tới autoload.php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function timeToSeconds($time) {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

function secondsToTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function groupById($array) {
    $result = [];
    
    foreach ($array as $item) {
        $id = $item['id'];
        if (!isset($result[$id])) {
            $result[$id] = [];
        }
        $result[$id][] = $item;
    }
    
    return $result;
}

function groupByDate($groupedData) {
    $result = [];
    
    foreach ($groupedData as $id => $items) {
        $result[$id] = [];
        foreach ($items as $item) {
            $date = $item['date'];
            if (!isset($result[$id][$date])) {
                $result[$id][$date] = [];
            }
            $result[$id][$date][] = $item;
        }
    }
    
    return $result;
}

function processCheckinCheckout($groupedDataByDate) {
    $result = [];
    
    foreach ($groupedDataByDate as $id => $dates) {
        foreach ($dates as $date => $items) {
            $checkinItem = $items[0];
            $checkoutItem = count($items) > 1 ? $items[0] : null;
            
            foreach ($items as $item) {
                if ($item['datetime'] < $checkinItem['datetime']) {
                    $checkinItem = $item;
                }
                if ($checkoutItem && $item['datetime'] > $checkoutItem['datetime']) {
                    $checkoutItem = $item;
                }
            }
            
            $result[] = [
                'id' => $id,
                'date' => $date,
                'checkin' => $checkinItem['hour'],
                'checkout' => $checkoutItem ? $checkoutItem['hour'] : ''
            ];
        }
    }
    
    return $result;
}

// Kiểm tra xem file có được tải lên không
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    // Đọc nội dung file
    $file_path = $_FILES['file']['tmp_name'];
    $file_content = file_get_contents($file_path);

    // Phân tách các dòng
    $lines = explode(PHP_EOL, trim($file_content));

    // Mảng để lưu trữ dữ liệu
    $data_array = [];

    // Duyệt qua từng dòng và phân tách các giá trị
    foreach ($lines as $line) {
        // Loại bỏ khoảng trắng thừa
        $line = trim($line);

        // Phân tách các giá trị theo dấu tab
        $values = preg_split('/\s+/', $line);

        // Thêm các giá trị vào mảng
        $data_array[] = [
            'id' => $values[0],
            'date' => $values[1],
            'hour' => $values[2],
            'datetime' => timeToSeconds($values[2]),
        ];
    }

    // Gọi hàm và xử lý kết quả
    $groupedDataById = groupById($data_array);
    $groupedDataByDate = groupByDate($groupedDataById);
    $result = processCheckinCheckout($groupedDataByDate);

    // Tạo đối tượng Spreadsheet mới
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Đặt tiêu đề cột
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Date');
    $sheet->setCellValue('C1', 'Checkin');
    $sheet->setCellValue('D1', 'Checkout');

    // Điền dữ liệu vào bảng
    $row = 2;
    foreach ($result as $data) {
        $sheet->setCellValue('A' . $row, $data['id']);
        $sheet->setCellValue('B' . $row, $data['date']);
        $sheet->setCellValue('C' . $row, $data['checkin']);
        $sheet->setCellValue('D' . $row, $data['checkout']);
        $row++;
    }

    // Xuất file Excel
    $writer = new Xlsx($spreadsheet);
    $filename = 'checkin_checkout_data.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
} else {
    echo "Có lỗi xảy ra khi tải lên file.";
}
?>
