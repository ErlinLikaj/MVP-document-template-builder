Add-Type -AssemblyName System.IO.Compression.FileSystem
$path = 'c:\Users\citad\Downloads\Developer_Task_Document_Generator.docx'
$zip = [System.IO.Compression.ZipFile]::OpenRead($path)
$entry = $zip.Entries | Where-Object { $_.FullName -eq 'word/document.xml' }
$stream = $entry.Open()
$reader = New-Object System.IO.StreamReader($stream)
$xml = $reader.ReadToEnd()
$reader.Close()
$stream.Close()
$zip.Dispose()
$text = $xml -replace '</w:p>', "`n" -replace '<[^>]+>', '' -replace '&lt;', '<' -replace '&gt;', '>' -replace '&amp;', '&'
$text = [regex]::Replace($text, '[ \t]+', ' ')
$text = [regex]::Replace($text, '(\r?\n){2,}', "`n`n")
Write-Output $text.Trim()
