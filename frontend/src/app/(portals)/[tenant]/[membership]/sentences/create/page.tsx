import { Suspense } from 'react';
import { redirect } from 'next/navigation';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import SentenceBuilderPage from '@/components/tenants/teams/sentences/SentenceBuilderPage';
import { getLanguages } from '@/app/_actions/tenants/team/language-actions';
import LoadingSpinner from '@/components/ui/loading-spinner';

interface PageProps {
  searchParams: {
    language_id?: string;
    word_ids?: string;
    return_url?: string;
  };
}

async function SentenceCreateContent({ searchParams }: PageProps) {
  // Get languages for the sentence builder
  const languagesResult = await getLanguages();
  
  if (languagesResult.error || !languagesResult.data) {
    throw new Error('Failed to load languages');
  }

  const languages = languagesResult.data.data.languages;

  // Parse initial data from search params
  const initialData = {
    language_id: searchParams.language_id ? parseInt(searchParams.language_id) : undefined,
    preselectedWords: searchParams.word_ids ? 
      searchParams.word_ids.split(',').map(id => ({ id, text: '' })) : 
      undefined,
    returnUrl: searchParams.return_url || '/admin/sentences'
  };

  return (
    <SentenceBuilderPage
      languages={languages}
      initialData={initialData}
    />
  );
}

export default async function SentenceCreatePage({ searchParams }: PageProps) {
  const session = await getServerSession(authOptions);
  
  if (!session) {
    redirect('/auth/login');
  }

  // Check if user has admin/team permissions
  if (!['admin', 'team'].includes(session.user.membership)) {
    redirect('/unauthorized');
  }

  return (
    <Suspense fallback={<LoadingSpinner />}>
      <SentenceCreateContent searchParams={searchParams} />
    </Suspense>
  );
}

export const metadata = {
  title: 'Create Sentence | Admin',
  description: 'Create new sentences for language learning content'
};
