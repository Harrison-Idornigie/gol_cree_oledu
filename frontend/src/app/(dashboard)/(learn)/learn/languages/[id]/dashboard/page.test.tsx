import { render, screen, waitFor } from '@testing-library/react';
import LanguageDashboardPage from './page';
import LanguageDashboard from '../../../LanguageDashboard';
import { getLanguageDashboard } from '@/app/_actions/user/language-actions';

// Mock the server action
jest.mock('@/app/_actions/user/language-actions', () => ({
  getLanguageDashboard: jest.fn(),
}));

// Mock the LanguageDashboard component
jest.mock('../../../LanguageDashboard', () => ({
  __esModule: true,
  default: jest.fn(() => <div data-testid="language-dashboard">Language Dashboard</div>),
}));

describe('LanguageDashboardPage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders the LanguageDashboard component with the correct language ID', () => {
    render(<LanguageDashboardPage params={{ id: '123' }} />);
    
    expect(LanguageDashboard).toHaveBeenCalledWith(
      { languageId: 123 },
      expect.anything()
    );
    
    expect(screen.getByTestId('language-dashboard')).toBeInTheDocument();
  });
});

describe('LanguageDashboard Integration', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('passes the correct language ID to the getLanguageDashboard function', async () => {
    // Mock implementation for testing
    (getLanguageDashboard as jest.Mock).mockResolvedValue({
      data: {
        language: {
          id: 123,
          name: 'Spanish',
          code: 'es',
          native_name: 'Español'
        },
        progress_summary: {
          progress_percentage: 45,
          completed_paths: 1,
          in_progress_paths: 2,
          not_started_paths: 3,
          lesson_stats: {
            completion_percentage: 30
          },
          quiz_stats: {
            completed: 5,
            total: 10
          }
        },
        recent_activities: [],
        recommendations: [],
        vocabulary_stats: {
          recent_vocabulary: []
        }
      },
      error: null
    });

    // We're not actually rendering the component here, just testing the integration
    // between the page and the server action
    const languageDashboard = new LanguageDashboard({ languageId: 123 });
    
    // Simulate the useEffect call
    await languageDashboard.constructor.prototype.render();
    
    expect(getLanguageDashboard).toHaveBeenCalledWith(123);
  });
});
