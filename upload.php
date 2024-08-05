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
if ((isset($_FILES['user_file']) && $_FILES['user_file']['error'] === UPLOAD_ERR_OK) &&
    (isset($_FILES['file-cham-cong']) && $_FILES['file-cham-cong']['error'] === UPLOAD_ERR_OK)) {
    $userResult = [];
    $result = [];

    // Kiểm tra và xử lý file người dùng
    if (isset($_FILES['user_file']) && $_FILES['user_file']['error'] === UPLOAD_ERR_OK) {
        // Đọc nội dung file người dùng
        $file_path = $_FILES['user_file']['tmp_name'];
        $file_content = file_get_contents($file_path);

        // Loại bỏ các ký tự đặc biệt không cần thiết, giữ lại chữ cái, số và khoảng trắng
        $file_content = preg_replace('/[^a-zA-Z0-9\s]/', '', $file_content);

        // Loại bỏ các chữ cái chỉ có 1 ký tự
        $file_content = preg_replace('/\b[a-zA-Z]\b/', '', $file_content);

        // Phân tách các dòng dựa trên ký tự phân cách (0x01)
        $lines = preg_split('/\x01/', trim($file_content));

        $userArray = [];

        foreach ($lines as $line) {
            // Loại bỏ khoảng trắng thừa
            $line = trim($line);

            // Phân tách các giá trị theo khoảng trắng
            $values = preg_split('/\s+/', $line);

            // Loại bỏ số phía sau nếu có hai số liền kề nhau
            $cleanedValues = [];
            $previousValueIsNumber = false;
            foreach ($values as $value) {
                if (preg_match('/^\d+$/', $value)) {
                    // Nếu giá trị là số
                    if ($previousValueIsNumber) {
                        // Nếu giá trị trước đó cũng là số
                        continue; // Bỏ qua số hiện tại
                    }
                    $previousValueIsNumber = true;
                } else {
                    $previousValueIsNumber = false;
                }
                $cleanedValues[] = $value;
            }

            if (count($cleanedValues) >= 2) {
                // Lấy giá trị đầu tiên làm ID và các giá trị còn lại làm tên
                $id = trim($cleanedValues[0]);
                $name = implode(' ', array_slice($cleanedValues, 1));
                $userArray[$id] = $name; // Đổi vị trí id và tên
            }
        }

        // Chuyển đổi mảng kết quả thành chuỗi với định dạng "Tên Số"
        $resultString = '';
        foreach ($userArray as $name => $number) {
            if ($resultString !== '') {
                $resultString .= ', ';
            }
            $resultString .= "$name $number";
        }

        // Chia chuỗi thành các phần tử dựa trên khoảng trắng
        $parts = explode(' ', $resultString);

        // Tạo mảng từ các phần tử
        $userResult = [];
        for ($i = 0; $i < count($parts); $i += 2) {
            $userResult[$parts[$i]] = (int)$parts[$i + 1];
        }
    }

    // Kiểm tra và xử lý file chấm công
    if (isset($_FILES['file-cham-cong']) && $_FILES['file-cham-cong']['error'] === UPLOAD_ERR_OK) {
        // Đọc nội dung file
        $file_path = $_FILES['file-cham-cong']['tmp_name'];
        $file_content = file_get_contents($file_path);

        // Phân tách các dòng
        $lines = explode(PHP_EOL, trim($file_content));

        // Mảng để lưu trữ dữ liệu
        $data_array = [];

        // Duyệt qua từng dòng và phân tách các giá trị
        foreach ($lines as $line) {
            // Loại bỏ khoảng trắng thừa
            $line = trim($line);

            // Phân tách các giá trị theo dấu tab hoặc khoảng trắng
            $values = preg_split('/\s+/', $line);

            if (count($values) >= 3) {
                // Thêm các giá trị vào mảng
                $data_array[] = [
                    'id' => $values[0],
                    'date' => $values[1],
                    'hour' => $values[2],
                    'datetime' => timeToSeconds($values[2]),
                ];
            }
        }

        // Gọi hàm và xử lý kết quả
        $groupedDataById = groupById($data_array);
        $groupedDataByDate = groupByDate($groupedDataById);
        $result = processCheckinCheckout($groupedDataByDate);

        // Mảng ánh xạ từ ID đến tên người dùng
        $idToNameMap = array_flip($userResult);

        // Kết quả cần tạo
        $finalArray = [];

        // Duyệt qua mảng result và ánh xạ ID đến tên người dùng
        foreach ($result as $entry) {
            $id = $entry['id'];
            $name = isset($idToNameMap[$id]) ? $idToNameMap[$id] : 'Unknown';

            $finalArray[] = [
                'id' => $id,
                'name' => $name,
                'date' => $entry['date'],
                'checkin' => $entry['checkin'],
                'checkout' => $entry['checkout']
            ];
        }

        // Tạo đối tượng Spreadsheet mới
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Đặt tiêu đề cột
        $sheet->setCellValue('A1', 'Nhân viên');
        $sheet->setCellValue('B1', 'Ngày');
        $sheet->setCellValue('C1', 'Giờ vào');
        $sheet->setCellValue('D1', 'Giờ ra');

        // Điền dữ liệu vào bảng
        $row = 2;
        foreach ($finalArray as $data) {
            $sheet->setCellValue('A' . $row, $data['name']);
            $sheet->setCellValue('B' . $row, $data['date']);
            $sheet->setCellValue('C' . $row, $data['checkin']);
            $sheet->setCellValue('D' . $row, $data['checkout']);
            $row++;
        }

        // Xuất file Excel
        $writer = new Xlsx($spreadsheet);
        $filename = 'cham-cong.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
                // In kết quả
                echo '<pre>';
                print_r($finalArray);
                echo '</pre>';
        exit;
    }
} else {
    echo "Có lỗi xảy ra khi tải lên file.";
}