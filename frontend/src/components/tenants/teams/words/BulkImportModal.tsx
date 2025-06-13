'use client';

import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Upload, 
  FileText, 
  Download, 
  AlertCircle, 
  CheckCircle,
  X
} from 'lucide-react';
import { toast } from 'sonner';
import { bulkImportWords } from '@/app/_actions/tenants/team/word-actions';

interface Language {
  id: number;
  code: string;
  name: string;
  native_name?: string;
}

interface BulkImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onImport: () => Promise<void>;
  languages: Language[];
}

interface ParsedWord {
  text: string;
  language_id: number;
  pronunciation_key?: string;
  part_of_speech?: string;
  translations?: Array<{
    text: string;
    language_id: number;
    pronunciation_key?: string;
    context_notes?: string;
  }>;
  error?: string;
}

export function BulkImportModal({ isOpen, onClose, onImport, languages }: BulkImportModalProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [operation, setOperation] = useState<'create' | 'update'>('create');
  const [activeTab, setActiveTab] = useState<'file' | 'text'>('file');
  const [csvFile, setCsvFile] = useState<File | null>(null);
  const [textInput, setTextInput] = useState('');
  const [defaultLanguageId, setDefaultLanguageId] = useState<number>(0);
  const [parsedWords, setParsedWords] = useState<ParsedWord[]>([]);
  const [showPreview, setShowPreview] = useState(false);

  // Handle file upload
  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
        setCsvFile(file);
        parseCSVFile(file);
      } else {
        toast.error('Please select a CSV file');
      }
    }
  };

  // Parse CSV file
  const parseCSVFile = async (file: File) => {
    try {
      const text = await file.text();
      const parsed = parseCSVText(text);
      setParsedWords(parsed);
      setShowPreview(true);
    } catch (error) {
      toast.error('Error parsing CSV file');
      console.error('CSV parsing error:', error);
    }
  };

  // Parse text input
  const parseTextInput = () => {
    if (!textInput.trim()) {
      toast.error('Please enter some text to parse');
      return;
    }

    try {
      const parsed = parseCSVText(textInput);
      setParsedWords(parsed);
      setShowPreview(true);
    } catch (error) {
      toast.error('Error parsing text input');
      console.error('Text parsing error:', error);
    }
  };

  // Parse CSV text (common logic for file and text input)
  const parseCSVText = (csvText: string): ParsedWord[] => {
    const lines = csvText.trim().split('\n');
    const words: ParsedWord[] = [];

    // Skip header if it exists
    const startIndex = lines[0].toLowerCase().includes('word') || lines[0].toLowerCase().includes('text') ? 1 : 0;

    for (let i = startIndex; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;

      try {
        const columns = parseCSVLine(line);
        const word = parseWordFromColumns(columns);
        words.push(word);
      } catch (error) {
        words.push({
          text: line,
          language_id: defaultLanguageId,
          error: `Error parsing line ${i + 1}: ${error instanceof Error ? error.message : 'Unknown error'}`,
        });
      }
    }

    return words;
  };

  // Parse individual CSV line
  const parseCSVLine = (line: string): string[] => {
    const result: string[] = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
      const char = line[i];
      
      if (char === '"') {
        inQuotes = !inQuotes;
      } else if (char === ',' && !inQuotes) {
        result.push(current.trim());
        current = '';
      } else {
        current += char;
      }
    }
    
    result.push(current.trim());
    return result;
  };

  // Parse word from CSV columns
  const parseWordFromColumns = (columns: string[]): ParsedWord => {
    if (columns.length < 1) {
      throw new Error('Missing word text');
    }

    const word: ParsedWord = {
      text: columns[0],
      language_id: defaultLanguageId,
    };

    // Optional: pronunciation (column 2)
    if (columns[1]) {
      word.pronunciation_key = columns[1];
    }

    // Optional: part of speech (column 3)
    if (columns[2]) {
      word.part_of_speech = columns[2];
    }

    // Optional: translation (column 4) and translation language (column 5)
    if (columns[3] && columns[4]) {
      const translationLang = languages.find(l => 
        l.code.toLowerCase() === columns[4].toLowerCase() ||
        l.name.toLowerCase() === columns[4].toLowerCase()
      );

      if (translationLang) {
        word.translations = [{
          text: columns[3],
          language_id: translationLang.id,
          pronunciation_key: columns[5] || undefined,
          context_notes: columns[6] || undefined,
        }];
      }
    }

    // Validation
    if (!word.text.trim()) {
      word.error = 'Word text is required';
    }

    if (word.language_id === 0) {
      word.error = 'Default language must be selected';
    }

    return word;
  };

  // Handle import
  const handleImport = async () => {
    const validWords = parsedWords.filter(word => !word.error);
    
    if (validWords.length === 0) {
      toast.error('No valid words to import');
      return;
    }

    setIsLoading(true);
    try {
      // Create FormData for the bulk import
      const formData = new FormData();
      formData.append('operation', operation);
      formData.append('words', JSON.stringify(validWords));

      const result = await bulkImportWords(formData);
      
      if (result.error) {
        toast.error(result.error);
        return;
      }

      const createdCount = result.data?.created || 0;
      const updatedCount = result.data?.updated || 0;
      toast.success(`Successfully imported ${createdCount + updatedCount} words`);
      
      await onImport();
      resetForm();
      onClose();
    } catch (error) {
      console.error('Import error:', error);
      toast.error('Failed to import words');
    } finally {
      setIsLoading(false);
    }
  };

  // Reset form
  const resetForm = () => {
    setCsvFile(null);
    setTextInput('');
    setParsedWords([]);
    setShowPreview(false);
    setActiveTab('file');
  };

  // Download template
  const downloadTemplate = () => {
    const template = `word,pronunciation,part_of_speech,translation,translation_language,translation_pronunciation,context_notes
hello,/həˈloʊ/,interjection,bonjour,french,/bonˈʒuʁ/,greeting
world,/wɜːrld/,noun,monde,french,/mɔ̃d/,earth or planet`;

    const blob = new Blob([template], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'word_import_template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  const validWords = parsedWords.filter(word => !word.error);
  const errorWords = parsedWords.filter(word => word.error);

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Bulk Import Words</DialogTitle>
          <DialogDescription>
            Import multiple words from CSV file or text input
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          {/* Operation Selection */}
          <div className="space-y-2">
            <Label>Import Operation</Label>
            <Select value={operation} onValueChange={(value) => setOperation(value as 'create' | 'update')}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="create">Create new words</SelectItem>
                <SelectItem value="update">Update existing words</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Default Language */}
          <div className="space-y-2">
            <Label>Default Language</Label>
            <Select
              value={defaultLanguageId.toString()}
              onValueChange={(value) => setDefaultLanguageId(parseInt(value))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select default language" />
              </SelectTrigger>
              <SelectContent>
                {languages.map((language) => (
                  <SelectItem key={language.id} value={language.id.toString()}>
                    {language.name} ({language.code})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {!showPreview ? (
            <Tabs value={activeTab} onValueChange={(value: string) => setActiveTab(value as 'file' | 'text')}>
              <TabsList className="grid w-full grid-cols-2">
                <TabsTrigger value="file">Upload CSV File</TabsTrigger>
                <TabsTrigger value="text">Paste Text</TabsTrigger>
              </TabsList>

              <TabsContent value="file" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Upload className="h-5 w-5" />
                      Upload CSV File
                    </CardTitle>
                    <CardDescription>
                      Upload a CSV file with word data. Download the template for the correct format.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center gap-4">
                      <Input
                        type="file"
                        accept=".csv"
                        onChange={handleFileUpload}
                        className="flex-1"
                      />
                      <Button variant="outline" onClick={downloadTemplate}>
                        <Download className="mr-2 h-4 w-4" />
                        Template
                      </Button>
                    </div>
                    {csvFile && (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <FileText className="h-4 w-4" />
                        {csvFile.name} ({(csvFile.size / 1024).toFixed(1)} KB)
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="text" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <FileText className="h-5 w-5" />
                      Paste CSV Text
                    </CardTitle>
                    <CardDescription>
                      Paste CSV-formatted text directly. Use commas to separate columns.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <Textarea
                      placeholder="word,pronunciation,part_of_speech,translation,translation_language&#10;hello,/həˈloʊ/,interjection,bonjour,french&#10;world,/wɜːrld/,noun,monde,french"
                      value={textInput}
                      onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setTextInput(e.target.value)}
                      rows={8}
                    />
                    <Button onClick={parseTextInput} disabled={!textInput.trim()}>
                      Parse Text
                    </Button>
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          ) : (
            /* Preview Section */
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold">Import Preview</h3>
                <Button variant="outline" onClick={() => setShowPreview(false)}>
                  <X className="mr-2 h-4 w-4" />
                  Back to Input
                </Button>
              </div>

              {/* Summary Stats */}
              <div className="grid grid-cols-3 gap-4">
                <Card>
                  <CardContent className="pt-4">
                    <div className="text-center">
                      <div className="text-2xl font-bold text-green-600">{validWords.length}</div>
                      <div className="text-sm text-muted-foreground">Valid Words</div>
                    </div>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-4">
                    <div className="text-center">
                      <div className="text-2xl font-bold text-red-600">{errorWords.length}</div>
                      <div className="text-sm text-muted-foreground">Errors</div>
                    </div>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-4">
                    <div className="text-center">
                      <div className="text-2xl font-bold">{parsedWords.length}</div>
                      <div className="text-sm text-muted-foreground">Total Rows</div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Error Words */}
              {errorWords.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-red-600">
                      <AlertCircle className="h-5 w-5" />
                      Errors ({errorWords.length})
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-2 max-h-32 overflow-y-auto">
                      {errorWords.map((word, index) => (
                        <div key={index} className="flex items-center gap-2 text-sm">
                          <Badge variant="destructive">Error</Badge>
                          <span className="font-mono">{word.text}</span>
                          <span className="text-red-600">- {word.error}</span>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Valid Words Preview */}
              {validWords.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-green-600">
                      <CheckCircle className="h-5 w-5" />
                      Valid Words ({validWords.length})
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-2 max-h-64 overflow-y-auto">
                      {validWords.slice(0, 10).map((word, index) => (
                        <div key={index} className="flex items-center gap-2 text-sm">
                          <Badge variant="secondary">
                            {languages.find(l => l.id === word.language_id)?.code}
                          </Badge>
                          <span className="font-medium">{word.text}</span>
                          {word.pronunciation_key && (
                            <span className="text-muted-foreground">/{word.pronunciation_key}/</span>
                          )}
                          {word.part_of_speech && (
                            <Badge variant="outline">{word.part_of_speech}</Badge>
                          )}
                          {word.translations && word.translations.length > 0 && (
                            <span className="text-blue-600">
                              → {word.translations[0].text}
                            </span>
                          )}
                        </div>
                      ))}
                      {validWords.length > 10 && (
                        <div className="text-sm text-muted-foreground text-center pt-2">
                          ... and {validWords.length - 10} more words
                        </div>
                      )}
                    </div>
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          {showPreview && validWords.length > 0 && (
            <Button onClick={handleImport} disabled={isLoading}>
              {isLoading ? 'Importing...' : `Import ${validWords.length} Words`}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}