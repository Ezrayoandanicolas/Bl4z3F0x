<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Folder and Subfolder</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .file-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .file-item button {
            margin-right: 0.5rem; /* Button on the left */
        }
        .hidden {
            display: none;
        }
        textarea {
            width: 100%;
            height: 200px;
            resize: vertical;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 font-sans flex justify-center">

    <div class="w-full max-w-4xl p-6 bg-white shadow-lg rounded-lg mt-4 mb-4">
        <h1 class="text-3xl font-bold mb-6 text-center">Tools Bl4z3F0x</h1>
        <div class="text-center mb-6 space-y-4">
            <button id="scanFolderBtn" class="bg-blue-500 text-white py-2 px-6 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">Scan Folder</button>
            <button id="scanFileBtn" class="bg-green-500 text-white py-2 px-6 rounded hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">Scan File</button>
            <button id="generateFileBtn" class="bg-purple-500 text-white py-2 px-6 rounded hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50">Generate File</button>
        </div>
        <div id="result" class="overflow-y-auto max-h-[80vh]"></div>
        <div id="resultGenerate" class="overflow-y-auto max-h-[80vh] mt-4">
            <textarea id="generatedFilesTextarea" class="border border-gray-300 p-2 rounded-lg" readonly></textarea>
        </div>
    </div>

    <script>
    function fetchData(url, callback, resultDivId) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var resultDiv = document.getElementById(resultDivId);
                if (!resultDiv) {
                    console.error("Element with ID '" + resultDivId + "' not found.");
                    return;
                }

                if (xhr.status === 200) {
                    console.log("Raw response:", xhr.responseText);
                    try {
                        var response = JSON.parse(xhr.responseText);
                        console.log("Parsed JSON:", response);
                        if (resultDivId === 'result') {
                            displayResult(response, resultDiv);
                        } else if (resultDivId === 'resultGenerate') {
                            displayGeneratedFiles(response);
                        }
                        if (callback) callback(response);
                    } catch (e) {
                        console.error("Invalid JSON:", xhr.responseText);
                        resultDiv.innerHTML = '<p class="text-red-500">Failed to parse JSON response.</p>';
                    }
                } else {
                    console.error("HTTP Error:", xhr.status, xhr.statusText);
                    resultDiv.innerHTML = '<p class="text-red-500">Failed to load data. Status: ' + xhr.status + '</p>';
                }
            }
        };
        xhr.send();
    }

    function performAction(action, filePath, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'scan.php?action=' + action, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        console.log(filePath);

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        alert(response.message || 'Action completed.');
                        if (callback) callback();
                    } catch (e) {
                        console.error("Invalid JSON:", xhr.responseText);
                        alert('Failed to parse server response.');
                    }
                } else {
                    console.error("HTTP Error:", xhr.status, xhr.statusText);
                    alert('Failed to perform action: ' + xhr.statusText);
                }
            }
        };

        xhr.send('file=' + encodeURIComponent(filePath)); // Send the file parameter
    }

    function displayResult(data, parent) {
        parent.innerHTML = ''; // Clear previous content

        if (Array.isArray(data)) {
            // If there are no matches or categories
            if (data.length === 0) {
                parent.innerHTML = '<p class="text-gray-500">No results found.</p>';
                return;
            }

            // Object to hold grouped data by category
            var groupedData = {};

            // Group the files by their match categories
            data.forEach(function(item) {
                for (var category in item.matches) {
                    if (item.matches.hasOwnProperty(category)) {
                        if (!groupedData[category]) {
                            groupedData[category] = [];
                        }
                        groupedData[category].push(item);
                    }
                }
            });

            // If no categories are found, display all items in a single list
            if (Object.keys(groupedData).length === 0) {
                // Display all items directly
                data.forEach(function(item) {
                    // Create a container for each file
                    var fileContainer = document.createElement('div');
                    fileContainer.className = 'mb-4 p-4 rounded-lg shadow-md';

                    // Add file path
                    var filePath = document.createElement('h3');
                    filePath.textContent = item.current_path;
                    filePath.className = 'text-lg font-semibold mb-2';
                    fileContainer.appendChild(filePath);

                    // Add matched tokens
                    var tokenList = document.createElement('ul');
                    tokenList.className = 'list-disc list-inside text-gray-800';
                    for (var category in item.matches) {
                        if (item.matches.hasOwnProperty(category)) {
                            item.matches[category].forEach(function(token) {
                                var tokenItem = document.createElement('li');
                                tokenItem.textContent = token;
                                tokenList.appendChild(tokenItem);
                            });
                        }
                    }
                    fileContainer.appendChild(tokenList);

                    // Add actions (view, delete)
                    var actionContainer = document.createElement('div');
                    actionContainer.className = 'mt-2 flex space-x-4';

                    // View button
                    var viewButton = document.createElement('button');
                    viewButton.textContent = 'View';
                    viewButton.className = 'px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700';
                    viewButton.onclick = function() {
                        window.open(item.current_path, '_blank');
                    };
                    actionContainer.appendChild(viewButton);

                    // Delete button
                    if (!item.deleted) {
                        var deleteButton = document.createElement('button');
                        deleteButton.textContent = 'Delete';
                        deleteButton.className = 'px-4 py-2 bg-red-500 text-white rounded hover:bg-red-700';
                        deleteButton.onclick = function() {
                            if (confirm('Are you sure you want to delete ' + item.current_path + '?')) {
                                performAction('deleteFile', item.current_path, function() {
                                    item.deleted = true; // Mark as deleted
                                    displayResult(data, parent); // Refresh the result after delete
                                });
                            }
                        };
                        actionContainer.appendChild(deleteButton);
                    }

                    fileContainer.appendChild(actionContainer);
                    parent.appendChild(fileContainer);
                });
            } else {
                // Display the grouped data
                for (var category in groupedData) {
                    if (groupedData.hasOwnProperty(category)) {
                        var cardContainer = document.createElement('div');
                        cardContainer.className = 'mb-6 p-4 bg-white rounded-lg shadow-lg';

                        var categoryTitle = document.createElement('h2');
                        categoryTitle.textContent = category;
                        categoryTitle.className = 'text-2xl font-bold mb-4 text-gray-900 cursor-pointer';
                        categoryTitle.onclick = function() {
                            var content = this.nextElementSibling;
                            content.classList.toggle('hidden');
                        };
                        cardContainer.appendChild(categoryTitle);

                        var contentContainer = document.createElement('div');
                        contentContainer.className = 'hidden';

                        groupedData[category].forEach(function(item) {
                            var fileContainer = document.createElement('div');
                            fileContainer.className = 'mb-4 p-4 rounded-lg shadow-md';

                            var filePath = document.createElement('h3');
                            filePath.textContent = item.current_path;
                            filePath.className = 'text-lg font-semibold mb-2';
                            if (item.deleted) {
                                filePath.classList.add('text-red-500');
                            } else {
                                filePath.classList.add('text-blue-700');
                            }
                            fileContainer.appendChild(filePath);

                            var tokenList = document.createElement('ul');
                            tokenList.className = 'list-disc list-inside text-gray-800';
                            for (var category in item.matches) {
                                if (item.matches.hasOwnProperty(category)) {
                                    item.matches[category].forEach(function(token) {
                                        var tokenItem = document.createElement('li');
                                        tokenItem.textContent = token;
                                        tokenList.appendChild(tokenItem);
                                    });
                                }
                            }
                            fileContainer.appendChild(tokenList);

                            var actionContainer = document.createElement('div');
                            actionContainer.className = 'mt-2 flex space-x-4';

                            var viewButton = document.createElement('button');
                            viewButton.textContent = 'View';
                            viewButton.className = 'px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700';
                            viewButton.onclick = function() {
                                window.open(item.current_path, '_blank');
                            };
                            actionContainer.appendChild(viewButton);

                            if (!item.deleted) {
                                var deleteButton = document.createElement('button');
                                deleteButton.textContent = 'Delete';
                                deleteButton.className = 'px-4 py-2 bg-red-500 text-white rounded hover:bg-red-700';
                                deleteButton.onclick = function() {
                                    if (confirm('Are you sure you want to delete ' + item.current_path + '?')) {
                                        performAction('deleteFile', item.current_path, function() {
                                            item.deleted = true; // Mark as deleted
                                            displayResult(data, parent); // Refresh the result after delete
                                        });
                                    }
                                };
                                actionContainer.appendChild(deleteButton);
                            }

                            fileContainer.appendChild(actionContainer);
                            contentContainer.appendChild(fileContainer);
                        });

                        cardContainer.appendChild(contentContainer);
                        parent.appendChild(cardContainer);
                    }
                }
            }
        } else {
            parent.innerHTML = '<p class="text-red-500">Invalid data format received.</p>';
        }
    }

    function displayGeneratedFiles(data) {
        var textarea = document.getElementById('generatedFilesTextarea');
        if (textarea) {
            textarea.value = ''; // Clear previous content

            if (Array.isArray(data)) {
                data.forEach(function(filePath) {
                    textarea.value += filePath + '\n';
                });
            } else {
                console.error("Invalid data format for generated files.");
            }
        }
    }

    document.getElementById('scanFolderBtn').addEventListener('click', function() {
        fetchData('scan.php?action=scanFolder', null, 'result');
    });

    document.getElementById('scanFileBtn').addEventListener('click', function() {
        fetchData('scan.php?action=scanFile', null, 'result');
    });

    document.getElementById('generateFileBtn').addEventListener('click', function() {
        fetchData('scan.php?action=generateFile', null, 'resultGenerate');
    });
    </script>
</body>
</html>
