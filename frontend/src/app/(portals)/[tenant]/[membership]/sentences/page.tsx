import { Suspense } from 'react';
import { redirect } from 'next/navigation';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/lib/auth';
import SentencesManagementPage from '@/components/tenants/teams/sentences/SentencesManagementPage';
import LoadingSpinner from '@/components/ui/loading-spinner';

async function SentencesContent() {
  return <SentencesManagementPage />;
}

export default async function SentencesPage() {
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
      <SentencesContent />
    </Suspense>
  );
}

export const metadata = {
  title: 'Sentences | Admin',
  description: 'Manage sentences for language learning content'
};
