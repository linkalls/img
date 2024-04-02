<?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ファイルがアップロードされているか確認
    if (!isset($_FILES['file'])) {
      http_response_code(400);
      echo json_encode(['error' => 'No file was uploaded.']);
      exit;
    }

    $file = $_FILES['file'];

    // ファイルサイズのチェック（上限とします）
    if ($file['size'] > 1 * 1024 * 1024 * 1024) {
      http_response_code(400);
      echo json_encode(['error' => 'File is too large. Maximum size is 10MB.']);
      exit;
    }

    // ファイルタイプのチェック（ここではPNGとJPEGのみ許可します）
    $allowedTypes = ['image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid file type. Only PNG and JPEG are allowed.']);
      exit;
    }

    // ファイルの拡張子を取得
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    // ファイルを保存
    $filename = '/file/' . uniqid() . '.' . $extension;
    move_uploaded_file($file['tmp_name'], $filename);

    echo json_encode(['url' => $filename]);
    exit;
  }
?>

<!DOCTYPE html>
<html lang="ja">
 <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Steganography Tool for Files</title>
    <link href="tailwind.css" rel="stylesheet" />
    <style>
      .output {
        min-height: 100px;
      }
    </style>
 </head>
 <body class="bg-gray-100">
    <div class="container mx-auto p-4">
      <h1 class="text-2xl font-bold mb-4">Steganography Tool for Files</h1>
      <div>
        <label for="fileInput" class="block mb-2">画像ファイルを選択してください：</label>
        <input type="file" id="fileInput" class="mb-4" />
      </div>
      <div>
        <label for="fileToEmbed" class="block mb-2">埋め込むファイルを選択してください：</label>
        <input type="file" id="fileToEmbed" class="mb-4" />
      </div>
      <div>
        <input type="checkbox" id="myCheckbox" />
        <label for="myCheckbox">Twitterに投稿する</label>
      </div>

      <div>
        <button id="embedButton" class="bg-blue-500 text-white py-2 px-4 rounded mr-2">ファイルを埋め込む</button>
        <button id="extractButton" class="bg-blue-500 text-white py-2 px-4 rounded">ファイルを抽出する</button>
      </div>
      <div class="output mt-4">
        <pre id="outputArea" class="bg-gray-200 p-4"></pre>
      </div>
    </div>

    <script>
      document.getElementById("embedButton").addEventListener("click", function () {
        document.getElementById("outputArea").textContent = "埋め込み中です...";
        const fileInput = document.getElementById("fileInput");
        const fileToEmbed = document.getElementById("fileToEmbed");
        const file = fileInput.files[0];
        const fileToEmbedData = fileToEmbed.files[0];
        if (!file || !fileToEmbedData) {
          alert("ファイルを選択してください。");
          return;
        }

        if (!(file.type === "image/jpeg" || file.type === "image/png" || file.type === "image/webp" || file.type === "image/jpg")) {
          alert("JPEG、PNG、jpg、またはWebP形式の画像ファイル以外はアップロードできません。");
          return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
          const img = new Image();
          img.onload = function () {
            const canvas = document.createElement("canvas");
            const ctx = canvas.getContext("2d");
            canvas.width = img.width;
            canvas.height = img.height;
            ctx.drawImage(img, 0, 0);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

            const fileReader = new FileReader();
            fileReader.onload = function (e) {
              const arrayBuffer = e.target.result;
              const binaryData = new Uint8Array(arrayBuffer);
              const fileNameData = encodeFileNameToBinaryData(fileToEmbedData.name);
              const combinedData = new Uint8Array(fileNameData.length + binaryData.length);
              combinedData.set(fileNameData);
              combinedData.set(binaryData, fileNameData.length);
              const encodedData = encodeBinaryDataToAlphaChannel(combinedData, imageData);
              ctx.putImageData(encodedData, 0, 0);

              const data = atob(canvas.toDataURL("image/png").split(",")[1]);
              const array = new Uint8Array(data.length);
              for (let i = 0; i < data.length; i++) {
                array[i] = data.charCodeAt(i);
              }
              const blob = new Blob([array.buffer], { type: "image/png" });
              const url = URL.createObjectURL(blob);
              const link = document.createElement("a");
              link.href = url;
              // 元のファイル名に"_enc.png"を追加
              link.download = file.name.split('.').slice(0, -1).join('.') + "_enc.png";
            
              link.click();
              document.getElementById("outputArea").textContent = "";
            };
            fileReader.readAsArrayBuffer(fileToEmbedData);
          };
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
      });

      document.getElementById("extractButton").addEventListener("click", function () {
        document.getElementById("outputArea").textContent = "抽出中です...";
        const fileInput = document.getElementById("fileInput");
        const file = fileInput.files[0];
        if (!file) {
          alert("ファイルを選択してください。");
          return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
          const img = new Image();
          img.onload = function () {
            const canvas = document.createElement("canvas");
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

            const decodedData = decodeAlphaChannelToBinaryData(imageData);
            const fileName = decodeBinaryDataToFileName(decodedData);
            const fileData = decodedData.slice(fileName.length);
            const newFile = new File([fileData], fileName, { type: "application/octet-stream" });
            const url = URL.createObjectURL(newFile);
            const link = document.createElement("a");
            link.href = url;
            link.download = fileName;
            link.click();
            document.getElementById("outputArea").textContent = "";
          };
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
      });

      function decodeBinaryDataToFileName(binaryData) {
        const decoder = new TextDecoder('utf-8');
        const decodedFileName = decoder.decode(binaryData);
        const fileNameEndIndex = decodedFileName.indexOf('\0');
        return decodedFileName.substring(0, fileNameEndIndex);
      }

      function encodeFileNameToBinaryData(fileName) {
        const encoder = new TextEncoder();
        const encodedFileName = encoder.encode(fileName);
        return new Uint8Array(encodedFileName);
      }


      function encodeBinaryDataToAlphaChannel(binaryData, imageData) {
        if (!(binaryData instanceof Uint8Array)) {
          console.error("binaryData is not an instance of Uint8Array");
          return;
        }

        for (let i = 0; i < binaryData.byteLength; i++) {
          const byte = binaryData[i];
          imageData.data[i * 4 + 3] = byte;
        }
        return imageData;
      }

      function decodeAlphaChannelToBinaryData(imageData) {
        const binaryData = new Uint8Array(imageData.data.buffer, 0, imageData.data.length / 4);
        return binaryData;
      }
    </script>
 </body>
</html>