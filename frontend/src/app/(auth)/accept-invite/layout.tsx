import { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Accept Admin Invite',
  description: 'Accept your invitation to become an admin',
};

export default function AcceptInviteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-screen flex flex-col">
      <header className="border-b bg-white">
        <div className="container py-4">
          <div className="flex items-center">
            <div className="font-semibold text-lg">Language Learning Platform</div>
          </div>
        </div>
      </header>
      <main className="flex-1 bg-slate-50">
        {children}
      </main>
      <footer className="border-t bg-white">
        <div className="container py-4">
          <div className="text-center text-sm text-muted-foreground">
            &copy; {new Date().getFullYear()} Language Learning Platform. All rights reserved.
          </div>
        </div>
      </footer>
    </div>
  );
}