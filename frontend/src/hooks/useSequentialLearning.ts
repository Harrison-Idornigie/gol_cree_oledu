import { useEffect, useState } from 'react';
import axios from '@/lib/axios';

interface UseSequentialLearningProps {
  type: 'unit' | 'lesson' | 'section';
  id: number;
}

interface SequentialLearningState {
  isUnlocked: boolean;
  isLoading: boolean;
  error: string | null;
}

/**
 * Hook to check if content is unlocked based on sequential learning requirements
 */
export function useSequentialLearning({ type, id }: UseSequentialLearningProps): SequentialLearningState {
  const [state, setState] = useState<SequentialLearningState>({
    isUnlocked: false,
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    const checkAccess = async () => {
      try {
        setState(prev => ({ ...prev, isLoading: true }));
        
        // Make API call to check if content is unlocked
        const response = await axios.get(`/api/${type}s/${id}`);
        
        // Check if the content is unlocked
        const isUnlocked = response.data?.data?.is_unlocked ?? false;
        
        setState({
          isUnlocked,
          isLoading: false,
          error: null,
        });
      } catch (error: any) {
        // If we get a 403 with locked=true, it means the content is locked
        if (error.response?.status === 403 && error.response?.data?.locked) {
          setState({
            isUnlocked: false,
            isLoading: false,
            error: error.response?.data?.message || 'This content is locked',
          });
        } else {
          setState({
            isUnlocked: false,
            isLoading: false,
            error: 'Failed to check if content is unlocked',
          });
        }
      }
    };

    if (id) {
      checkAccess();
    }
  }, [type, id]);

  return state;
}
