"use client"

import { Lock } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { useRouter } from "next/navigation"

interface LockedContentProps {
  title: string
  message?: string
  redirectPath?: string
  redirectLabel?: string
}

/**
 * Component to display when content is locked
 */
export function LockedContent({
  title = "Content Locked",
  message = "You need to complete previous content before accessing this.",
  redirectPath,
  redirectLabel = "Go Back"
}: LockedContentProps) {
  const router = useRouter()

  return (
    <div className="flex items-center justify-center min-h-[50vh]">
      <Card className="w-full max-w-md border-2 border-amber-500">
        <CardHeader className="text-center">
          <div className="flex items-center justify-center w-16 h-16 p-3 mx-auto mb-4 rounded-full bg-amber-100">
            <Lock className="w-8 h-8 text-amber-500" />
          </div>
          <CardTitle className="text-xl font-bold text-amber-700">{title}</CardTitle>
          <CardDescription className="text-amber-600">{message}</CardDescription>
        </CardHeader>
        <CardContent className="text-sm text-center text-muted-foreground">
          <p>Complete the previous content to unlock this section.</p>
        </CardContent>
        {redirectPath && (
          <CardFooter className="flex justify-center">
            <Button 
              variant="outline" 
              className="border-amber-500 text-amber-700 hover:bg-amber-50"
              onClick={() => router.push(redirectPath)}
            >
              {redirectLabel}
            </Button>
          </CardFooter>
        )}
      </Card>
    </div>
  )
}
