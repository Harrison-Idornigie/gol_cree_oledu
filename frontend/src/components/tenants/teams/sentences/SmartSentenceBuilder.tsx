'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Lightbulb, CheckCircle, AlertTriangle, Wand2 } from 'lucide-react';
import { toast } from 'sonner';

interface Language {
  id: number;
  name: string;
  code: string;
}

interface SmartSentenceBuilderProps {
  languages: Language[];
  onSentenceCreated?: (sentence: any) => void;
  onCancel?: () => void;
  initialData?: {
    language_id?: number;
    text?: string;
  };
}

export default function SmartSentenceBuilder({
  languages,
  onSentenceCreated,
  onCancel,
  initialData
}: SmartSentenceBuilderProps) {
  const [formData, setFormData] = useState({
    text: initialData?.text || '',
    language_id: initialData?.language_id || (languages[0]?.id || 0),
    difficulty: 'beginner' as 'beginner' | 'intermediate' | 'advanced'
  });
  
  const [analysis, setAnalysis] = useState<any>(null);
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [isCreating, setIsCreating] = useState(false);
  const [showMissingWords, setShowMissingWords] = useState(false);
  const [missingWordData, setMissingWordData] = useState<Record<string, any>>({});

  // Auto-analyze when text changes
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (formData.text.trim() && formData.language_id) {
        analyzeText();
      }
    }, 1000);

    return () => clearTimeout(timeoutId);
  }, [formData.text, formData.language_id]);

  const analyzeText = async () => {
    if (!formData.text.trim() || !formData.language_id) return;

    setIsAnalyzing(true);
    try {
      // Mock analysis - replace with actual API call
      const mockAnalysis = {
        analysis: {
          mapped_words: [
            { word_id: 1, position: 0, text: 'tanisi', clean_text: 'tanisi', existing_word: {} },
          ],
          missing_words: [
            { text: 'nitôtem', clean_text: 'nîtotem', position: 1, suggested_part_of_speech: 'NA', suggested_pronunciation: 'nee-TOH-tem' }
          ],
          total_words: 2,
          mapping_percentage: 50
        },
        suggestions: {
          auto_create_missing: true,
          mapping_quality: 'good' as const,
          recommended_action: 'auto_create_recommended' as const
        }
      };
      setAnalysis(mockAnalysis);
      
      if (mockAnalysis.analysis.missing_words.length > 0) {
        setShowMissingWords(true);
        // Initialize missing word data
        const initialMissingData: Record<string, any> = {};
        mockAnalysis.analysis.missing_words.forEach(word => {
          initialMissingData[word.clean_text] = {
            pronunciation_key: word.suggested_pronunciation,
            part_of_speech: word.suggested_part_of_speech,
            difficulty: formData.difficulty,
            tags: ['auto-generated']
          };
        });
        setMissingWordData(initialMissingData);
      }
    } catch (error) {
      console.error('Analysis failed:', error);
    } finally {
      setIsAnalyzing(false);
    }
  };

  const handleCreateSentence = async (autoCreateWords: boolean = false) => {
    setIsCreating(true);
    try {
      // Mock creation - replace with actual API call
      const mockResult = {
        sentence: {
          id: 1,
          text: formData.text,
          language_id: formData.language_id
        },
        mapping_result: {
          mapped_words: analysis?.analysis.mapped_words || [],
          created_words: autoCreateWords ? analysis?.analysis.missing_words || [] : [],
          total_words: analysis?.analysis.total_words || 0
        },
        created_words: autoCreateWords ? analysis?.analysis.missing_words || [] : []
      };

      toast.success(
        autoCreateWords ? 'Sentence created with new words!' : 'Sentence created successfully!',
        {
          description: autoCreateWords 
            ? `Created ${mockResult.created_words.length} new words automatically`
            : 'All words were already in the database'
        }
      );

      if (onSentenceCreated) {
        onSentenceCreated(mockResult.sentence);
      }
    } catch (error) {
      console.error('Creation failed:', error);
      toast.error('Failed to create sentence');
    } finally {
      setIsCreating(false);
    }
  };

  const getQualityColor = (quality: string) => {
    switch (quality) {
      case 'excellent': return 'bg-green-100 text-green-800';
      case 'good': return 'bg-blue-100 text-blue-800';
      case 'fair': return 'bg-yellow-100 text-yellow-800';
      case 'poor': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getActionRecommendation = () => {
    if (!analysis?.suggestions) return null;

    const { recommended_action, auto_create_missing } = analysis.suggestions;
    
    switch (recommended_action) {
      case 'ready_to_create':
        return {
          type: 'success',
          title: 'Ready to create!',
          description: 'All words exist in the database. You can create this sentence immediately.',
          action: 'create'
        };
      case 'auto_create_recommended':
        return {
          type: 'info',
          title: 'Auto-creation recommended',
          description: `Only ${analysis.analysis.missing_words.length} words need to be created. We can do this automatically.`,
          action: 'auto_create'
        };
      case 'manual_review_recommended':
        return {
          type: 'warning',
          title: 'Manual review needed',
          description: 'Several words are missing. Please review the word details below.',
          action: 'manual_review'
        };
      case 'create_words_first':
        return {
          type: 'error',
          title: 'Create words first',
          description: 'Too many words are missing. Consider creating them individually first.',
          action: 'manual_create'
        };
      default:
        return null;
    }
  };

  const recommendation = getActionRecommendation();

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Wand2 className="h-5 w-5" />
            Smart Sentence Builder
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Language Selection */}
          <div className="space-y-2">
            <Label htmlFor="language">Language</Label>
            <select
              id="language"
              value={formData.language_id}
              onChange={(e) => setFormData(prev => ({ ...prev, language_id: parseInt(e.target.value) }))}
              className="w-full p-2 border rounded-md"
            >
              {languages.map(lang => (
                <option key={lang.id} value={lang.id}>{lang.name}</option>
              ))}
            </select>
          </div>

          {/* Sentence Text */}
          <div className="space-y-2">
            <Label htmlFor="text">Sentence Text</Label>
            <Textarea
              id="text"
              value={formData.text}
              onChange={(e) => setFormData(prev => ({ ...prev, text: e.target.value }))}
              placeholder="Type your sentence here..."
              className="min-h-[100px]"
            />
            {isAnalyzing && (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Analyzing sentence...
              </div>
            )}
          </div>

          {/* Difficulty */}
          <div className="space-y-2">
            <Label htmlFor="difficulty">Difficulty Level</Label>
            <select
              id="difficulty"
              value={formData.difficulty}
              onChange={(e) => setFormData(prev => ({ ...prev, difficulty: e.target.value as any }))}
              className="w-full p-2 border rounded-md"
            >
              <option value="beginner">Beginner</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
        </CardContent>
      </Card>

      {/* Analysis Results */}
      {analysis && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Lightbulb className="h-5 w-5" />
              Analysis Results
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {/* Mapping Quality */}
            <div className="flex items-center gap-4">
              <span className="text-sm font-medium">Mapping Quality:</span>
              <Badge className={getQualityColor(analysis.suggestions.mapping_quality)}>
                {analysis.suggestions.mapping_quality}
              </Badge>
              <span className="text-sm text-muted-foreground">
                ({Math.round(analysis.analysis.mapping_percentage)}% of words found)
              </span>
            </div>

            {/* Recommendation */}
            {recommendation && (
              <Alert>
                <AlertTriangle className="h-4 w-4" />
                <AlertDescription>
                  <strong>{recommendation.title}</strong><br />
                  {recommendation.description}
                </AlertDescription>
              </Alert>
            )}

            {/* Missing Words Section */}
            {showMissingWords && analysis.analysis.missing_words.length > 0 && (
              <div className="space-y-3">
                <h4 className="font-medium">Missing Words ({analysis.analysis.missing_words.length})</h4>
                <div className="space-y-3">
                  {analysis.analysis.missing_words.map((word: any, index: number) => (
                    <Card key={index} className="p-3">
                      <div className="space-y-2">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline">{word.text}</Badge>
                          <span className="text-xs text-muted-foreground">
                            Position {word.position + 1}
                          </span>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-sm">
                          <Input
                            placeholder="Pronunciation"
                            value={missingWordData[word.clean_text]?.pronunciation_key || ''}
                            onChange={(e) => setMissingWordData(prev => ({
                              ...prev,
                              [word.clean_text]: {
                                ...prev[word.clean_text],
                                pronunciation_key: e.target.value
                              }
                            }))}
                          />
                          <select
                            value={missingWordData[word.clean_text]?.part_of_speech || word.suggested_part_of_speech}
                            onChange={(e) => setMissingWordData(prev => ({
                              ...prev,
                              [word.clean_text]: {
                                ...prev[word.clean_text],
                                part_of_speech: e.target.value
                              }
                            }))}
                            className="p-2 border rounded"
                          >
                            <option value="NA">Animate Noun</option>
                            <option value="NI">Inanimate Noun</option>
                            <option value="VAI">Intransitive Verb</option>
                            <option value="VTA">Transitive Animate Verb</option>
                            <option value="VII">Intransitive Inanimate Verb</option>
                          </select>
                        </div>
                      </div>
                    </Card>
                  ))}
                </div>
              </div>
            )}

            {/* Action Buttons */}
            <div className="flex gap-2 pt-4">
              {recommendation?.action === 'create' && (
                <Button 
                  onClick={() => handleCreateSentence(false)}
                  disabled={isCreating}
                  className="flex items-center gap-2"
                >
                  {isCreating ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle className="h-4 w-4" />}
                  Create Sentence
                </Button>
              )}
              
              {(recommendation?.action === 'auto_create' || recommendation?.action === 'manual_review') && (
                <>
                  <Button 
                    onClick={() => handleCreateSentence(true)}
                    disabled={isCreating}
                    className="flex items-center gap-2"
                  >
                    {isCreating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Wand2 className="h-4 w-4" />}
                    Auto-Create Missing Words
                  </Button>
                  <Button 
                    variant="outline"
                    onClick={() => handleCreateSentence(false)}
                    disabled={isCreating || analysis.analysis.missing_words.length > 0}
                  >
                    Create Without Missing Words
                  </Button>
                </>
              )}
              
              {onCancel && (
                <Button variant="ghost" onClick={onCancel} disabled={isCreating}>
                  Cancel
                </Button>
              )}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
