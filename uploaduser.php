<?php
// Kiểm tra xem file có được tải lên không
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
        $result[$parts[$i]] = (int)$parts[$i + 1];
    }

    // In mảng kết quả
    echo'<pre>';
    print_r($userResult);
    echo'</pre>';
} else {
    echo "Có lỗi xảy ra khi tải lên file.";
}
?>
